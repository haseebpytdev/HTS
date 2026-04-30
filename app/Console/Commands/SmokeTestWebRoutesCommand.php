<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\AsyncTaskRun;
use App\Models\BlogPost;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Customer;
use App\Models\CustomerSavedTraveler;
use App\Models\FlightEntry;
use App\Models\GroupImage;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use App\Models\Inquiry;
use App\Models\IntegrationConnection;
use App\Models\LandingPage;
use App\Models\PackageImage;
use App\Models\Quotation;
use App\Models\SupplierSearchSession;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\TravelGroup;
use App\Models\TravelPackage;
use App\Models\User;
use App\Models\VisaType;
use App\Models\ServiceModule;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route as RouteFacade;
use Throwable;

class SmokeTestWebRoutesCommand extends Command
{
    protected $signature = 'app:smoke-web-routes
        {--admin-email=superadmin@apnasafar.test : Staff user email (super_admin / admin area)}
        {--agency-email=agency.lhr@apnasafar.test : Agency staff user email}
        {--include-api : Include GET api/* routes (often need API auth)}
        {--stop-on-error : Stop on first exception or HTTP 5xx}';

    protected $description = 'Run GET routes through the HTTP kernel (session + auth) and report status, redirects, exceptions, and slow first-hit timing for /';

    public function handle(): int
    {
        $this->components->info('ApnaSafar web route smoke test (uses in-process HTTP kernel, same bootstrap as php / artisan serve).');

        $kernel = app(HttpKernel::class);
        $homeMs = $this->measureFirstHomeDispatch($kernel);
        if ($homeMs !== null) {
            $this->line(sprintf('First isolated GET / (guest, full stack): %.0f ms', $homeMs));
        }

        $admin = User::query()->where('email', (string) $this->option('admin-email'))->first();
        if (! $admin) {
            $this->components->warn(sprintf('No staff user for --admin-email=%s (admin/area tests may 302 to login).', $this->option('admin-email')));
        }

        $agencyUser = User::query()->where('email', (string) $this->option('agency-email'))->first();
        if (! $agencyUser) {
            $this->components->warn(sprintf('No user for --agency-email=%s.', $this->option('agency-email')));
        }

        $customer = Customer::query()->first();
        if (! $customer) {
            $this->components->warn('No Customer row found; customer/* authenticated routes will be skipped or fail.');
        }

        $routes = array_values(array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            fn (Route $r) => $this->shouldTestRoute($r)
        ));

        $this->line(sprintf('Testing %d GET route(s).', count($routes)));

        $ok = 0;
        $warn = 0;
        $fail = 0;

        foreach ($routes as $route) {
            $path = null;
            $name = $route->getName() ?? '';
            try {
                $path = $this->buildPath($route);
            } catch (Throwable $e) {
                $this->printThrowable('FAIL', $route->uri(), $name, $e);
                $fail++;
                if ($this->option('stop-on-error')) {
                    return 1;
                }

                continue;
            }

            if ($path === null) {
                $this->components->warn(sprintf('SKIP (could not build URL) %s', $name !== '' ? $name : $route->uri()));
                $warn++;

                continue;
            }

            $authContext = $this->resolveAuthContext($route, $admin, $agencyUser, $customer);
            if ($authContext === false) {
                $this->line(sprintf('SKIP %-50s %s (missing auth principal)', $path, $name));
                $warn++;

                continue;
            }

            [$user, $guard] = $authContext;

            try {
                [$status, $location, $excerpt] = $this->dispatchGet($kernel, $path, $user, $guard);
            } catch (Throwable $e) {
                $this->printThrowable('FAIL', $path, $name, $e);
                $fail++;
                if ($this->option('stop-on-error')) {
                    return 1;
                }

                continue;
            }

            if ($status >= 500) {
                $this->error(sprintf('FAIL %-5d %-60s %s %s', $status, $path, $name, $excerpt ?? ''));
                $fail++;
                if ($this->option('stop-on-error')) {
                    return 1;
                }

                continue;
            }

            if ($status === 403 || $status === 404) {
                $this->components->warn(sprintf('WARN %-5d %-60s %s', $status, $path, $name));
                $warn++;

                continue;
            }

            if ($status >= 300 && $status < 400) {
                $this->line(sprintf('OK   %-5d %-60s %s -> %s', $status, $path, $name, $location ?? '?'));
                $ok++;

                continue;
            }

            if ($status >= 200 && $status < 300) {
                $this->line(sprintf('OK   %-5d %-60s %s', $status, $path, $name));
                $ok++;

                continue;
            }

            $this->components->warn(sprintf('WARN %-5d %-60s %s', $status, $path, $name));
            $warn++;
        }

        $this->newLine();
        $this->line(sprintf('Summary: OK=%d WARN=%d FAIL=%d', $ok, $warn, $fail));

        return $fail > 0 ? 1 : 0;
    }

    private function measureFirstHomeDispatch(HttpKernel $kernel): ?float
    {
        try {
            $t0 = microtime(true);
            [$status] = $this->dispatchGet($kernel, '/', null, null);
            $ms = (microtime(true) - $t0) * 1000;
            if ($status >= 500) {
                $this->error(sprintf('GET / returned %d — check logs and database connectivity.', $status));
            }

            return $ms;
        } catch (Throwable $e) {
            $this->printThrowable('FAIL', '/', 'frontend.home', $e);

            return null;
        }
    }

    /**
     * @return array{0: int, 1: ?string, 2: ?string} status, Location header, short body hint for errors
     */
    private function dispatchGet(HttpKernel $kernel, string $path, ?object $user, ?string $guard): array
    {
        $request = Request::create($path, 'GET', [], [], [], $this->serverVars());

        /** @var \Illuminate\Session\Store $session */
        $session = app('session.store');
        $session->flush();
        if (! $session->isStarted()) {
            $session->start();
        }
        $request->setLaravelSession($session);

        Auth::guard('web')->logout();
        Auth::guard('customer')->logout();

        if ($user !== null) {
            if ($guard === 'customer') {
                Auth::guard('customer')->login($user);
            } else {
                Auth::guard('web')->login($user);
            }
            $session->save();
        }

        $response = $kernel->handle($request);
        try {
            $status = $response->getStatusCode();
            $location = $response->headers->get('Location');
            $excerpt = null;
            if ($status >= 500) {
                $content = $response->getContent();
                $excerpt = is_string($content) ? mb_substr(preg_replace('/\s+/', ' ', $content) ?? '', 0, 120) : null;
            }

            return [$status, $location, $excerpt];
        } finally {
            $kernel->terminate($request, $response);
        }
    }

    /**
     * @return array{0: ?object, 1: ?string} user model and guard name (web|customer); false = skip route
     */
    private function resolveAuthContext(Route $route, ?User $admin, ?User $agencyUser, ?Customer $customer): array|false
    {
        $middleware = $route->gatherMiddleware();
        $uri = $route->uri();

        if (in_array('guest', $middleware, true) || in_array('customer.guest', $middleware, true)) {
            return [null, null];
        }

        if (str_starts_with($uri, 'customer/')) {
            $names = $route->gatherMiddleware();
            if (in_array('auth:customer', $names, true) || $this->middlewareUsesAuthCustomer($names)) {
                if (! $customer instanceof Customer) {
                    return false;
                }

                return [$customer, 'customer'];
            }

            return [null, null];
        }

        if (str_starts_with($uri, 'admin/')) {
            return [$admin, 'web'];
        }

        if (str_starts_with($uri, 'agency/')) {
            return [$agencyUser, 'web'];
        }

        if ($this->middlewareUsesAuthCustomer($middleware)) {
            if (! $customer instanceof Customer) {
                return false;
            }

            return [$customer, 'customer'];
        }

        if ($this->middlewareUsesWebAuth($middleware)) {
            return [$admin, 'web'];
        }

        return [null, null];
    }

    /**
     * @param  list<string>  $middleware
     */
    private function middlewareUsesAuthCustomer(array $middleware): bool
    {
        foreach ($middleware as $m) {
            if ($m === 'auth:customer' || str_starts_with((string) $m, 'auth:customer')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $middleware
     */
    private function middlewareUsesWebAuth(array $middleware): bool
    {
        foreach ($middleware as $m) {
            $m = (string) $m;
            if ($m === 'auth' || $m === 'auth:web' || (str_starts_with($m, 'auth:') && ! str_starts_with($m, 'auth:customer'))) {
                return true;
            }
        }

        return false;
    }

    private function shouldTestRoute(Route $route): bool
    {
        if (! in_array('GET', $route->methods(), true)) {
            return false;
        }

        $uri = $route->uri();
        if (str_starts_with($uri, '_')) {
            return false;
        }

        if (str_starts_with($uri, 'api/') && ! $this->option('include-api')) {
            return false;
        }

        // Telescope / Horizon stubs if present
        if (str_starts_with($uri, 'telescope') || str_starts_with($uri, 'horizon')) {
            return false;
        }

        return true;
    }

    private function buildPath(Route $route): ?string
    {
        $name = $route->getName();
        $params = $this->resolveRouteParameters($route);

        try {
            if ($name && ! str_starts_with($name, 'generated::')) {
                return route($name, $params, false);
            }
        } catch (Throwable) {
            // fall through to manual path
        }

        $uri = $route->uri();
        foreach ($route->parameterNames() as $param) {
            $value = $params[$param] ?? null;
            if ($value === null) {
                return null;
            }
            $uri = preg_replace('/\{'.preg_quote($param, '/').'[^}]*\}/', rawurlencode((string) $value), (string) $uri, 1);
        }

        if ($uri !== null && str_contains((string) $uri, '{')) {
            return null;
        }

        $path = '/'.ltrim((string) $uri, '/');

        return $path === '/' ? '/' : $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRouteParameters(Route $route): array
    {
        $out = [];
        $routeName = $route->getName() ?? '';

        foreach ($route->parameterNames() as $param) {
            $out[$param] = $this->resolveParameterValue($param, $routeName, $route);
        }

        return $out;
    }

    private function resolveParameterValue(string $param, string $routeName, Route $route): mixed
    {
        try {
            return $this->resolveParameterValueFromDatabase($param, $routeName, $route);
        } catch (QueryException) {
            return match ($param) {
                'slug' => 'smoke-fallback-slug',
                'token' => 'smoke-reset-token',
                'hash' => 'smoke',
                'accountKey' => 'smoke-account-key',
                'role' => 'super_admin',
                'section' => 'general',
                'module' => 'flight_search',
                default => 1,
            };
        }
    }

    private function resolveParameterValueFromDatabase(string $param, string $routeName, Route $route): mixed
    {
        if ($param === 'token') {
            return 'smoke-reset-token';
        }

        if ($param === 'hash') {
            return 'smoke';
        }

        if ($param === 'slug') {
            if (str_contains($routeName, 'packages')) {
                return TravelPackage::query()->whereNotNull('slug')->value('slug') ?? 'missing-package-slug';
            }
            if (str_contains($routeName, 'groups')) {
                return TravelGroup::query()->whereNotNull('slug')->value('slug') ?? 'missing-group-slug';
            }
            if (str_contains($routeName, 'blog')) {
                return BlogPost::query()->whereNotNull('slug')->value('slug') ?? 'missing-blog-slug';
            }
            if (str_contains($routeName, 'landing')) {
                return LandingPage::query()->whereNotNull('slug')->value('slug') ?? 'missing-landing-slug';
            }

            return 'smoke-slug';
        }

        if ($param === 'id' && str_contains($routeName, 'verify-email')) {
            return User::query()->value('id') ?? 1;
        }

        if ($param === 'role') {
            return 'super_admin';
        }

        if ($param === 'section') {
            return 'general';
        }

        if ($param === 'module') {
            return ServiceModule::query()->value('code') ?? 'flight_search';
        }

        if ($param === 'tenant') {
            return Tenant::query()->value('id') ?? 1;
        }

        if ($param === 'accountKey') {
            return IntegrationConnection::query()->whereNotNull('account_key')->value('account_key') ?? 'smoke-account-key';
        }

        if ($param === 'connection') {
            return IntegrationConnection::query()->value('id') ?? 1;
        }

        if ($param === 'integration') {
            return IntegrationConnection::query()->value('id') ?? 1;
        }

        if ($param === 'searchSession') {
            return SupplierSearchSession::query()->value('id') ?? 1;
        }

        if ($param === 'supportTicket') {
            return SupportTicket::query()->value('id') ?? 1;
        }

        if ($param === 'document') {
            $bookingId = Booking::query()->value('id');

            return BookingDocument::query()->when($bookingId, fn ($q) => $q->where('booking_id', $bookingId))->value('id') ?? 1;
        }

        return match ($param) {
            'package' => TravelPackage::query()->value('id') ?? 1,
            'group' => TravelGroup::query()->value('id') ?? 1,
            'packageImage' => PackageImage::query()->where('travel_package_id', TravelPackage::query()->value('id') ?? 0)->value('id') ?? 1,
            'groupImage' => GroupImage::query()->where('travel_group_id', TravelGroup::query()->value('id') ?? 0)->value('id') ?? 1,
            'inquiry' => Inquiry::query()->value('id') ?? 1,
            'agency' => Agency::query()->value('id') ?? 1,
            'quotation' => Quotation::query()->value('id') ?? 1,
            'booking' => Booking::query()->value('id') ?? 1,
            'saved_traveler' => CustomerSavedTraveler::query()->value('id') ?? 1,
            'taskRun', 'task_run' => AsyncTaskRun::query()->value('id') ?? 1,
            'flight' => FlightEntry::query()->value('id') ?? 1,
            'hotel' => Hotel::query()->value('id') ?? 1,
            'hotel_room_type', 'hotelRoomType' => HotelRoomType::query()->value('id') ?? 1,
            'visaType', 'visa_type' => VisaType::query()->value('id') ?? 1,
            default => 1,
        };
    }

    /**
     * @return array<string, string>
     */
    private function serverVars(): array
    {
        return [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
            'HTTP_HOST' => '127.0.0.1',
            'SERVER_NAME' => '127.0.0.1',
            'SERVER_PORT' => '8000',
            'REMOTE_ADDR' => '127.0.0.1',
            'REQUEST_SCHEME' => 'http',
            'HTTPS' => 'off',
        ];
    }

    private function printThrowable(string $label, string $path, string $name, Throwable $e): void
    {
        $this->error(sprintf(
            '%s %s %s — %s in %s:%d',
            $label,
            $path,
            $name,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        $prev = $e->getPrevious();
        if ($prev instanceof Throwable) {
            $this->line(sprintf('  Caused by: %s in %s:%d', $prev->getMessage(), $prev->getFile(), $prev->getLine()));
        }

        $trace = $e->getTraceAsString();
        $this->line('  Trace (first 800 chars):');
        $this->line(mb_substr($trace, 0, 800));
    }
}
