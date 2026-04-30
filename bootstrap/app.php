<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnforceSessionMaxAge;
use App\Http\Middleware\RedirectIfCustomerAuthenticated;
use App\Http\Middleware\ShareFrontendSeo;
use App\Http\Middleware\EnsureIntegrationApiAuth;
use App\Http\Middleware\EnsureIntegrationIdempotency;
use App\Http\Middleware\EnsureApprovalGate;
use App\Http\Middleware\LogComplianceAuditTrail;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(base_path('routes/customer.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(function (Request $request): string {
            $path = $request->path();
            if ($path === 'customer' || str_starts_with($path, 'customer/')) {
                return route('customer.login', absolute: false);
            }

            return route('login', absolute: false);
        });

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'permission' => EnsureUserHasPermission::class,
            'frontend.seo' => ShareFrontendSeo::class,
            'customer.guest' => RedirectIfCustomerAuthenticated::class,
            'integration.auth' => EnsureIntegrationApiAuth::class,
            'integration.idempotency' => EnsureIntegrationIdempotency::class,
            'compliance.audit' => LogComplianceAuditTrail::class,
            'approval.gate' => EnsureApprovalGate::class,
            'session.max_age' => EnforceSessionMaxAge::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! Str::startsWith($request->path(), 'api/v1/integrations/')) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $code = match (true) {
                $status === 401 => 'unauthorized',
                $status === 403 => 'forbidden',
                $status === 404 => 'not_found',
                $status === 422 => 'validation_error',
                $status === 429 => 'rate_limited',
                default => 'integration_error',
            };

            return response()->json([
                'error' => [
                    'code' => $code,
                    'message' => $status >= 500 ? 'Integration request failed.' : $e->getMessage(),
                    'correlation_id' => $request->header('X-Correlation-Id'),
                ],
            ], $status);
        });
    })->create();
