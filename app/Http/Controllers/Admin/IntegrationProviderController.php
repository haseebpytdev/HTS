<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Models\IntegrationConnection;
use App\Models\ServiceModule;
use App\Models\ServiceModuleHealthCheck;
use Illuminate\View\View;

class IntegrationProviderController extends Controller
{
    public function index(): View
    {
        $providers = collect(['travelport', 'sabre', AmadeusSelfServiceProvider::CODE, 'iati', 'duffel'])->map(function (string $provider): array {
            $rows = IntegrationConnection::query()
                ->when(
                    AmadeusSelfServiceProvider::matches($provider),
                    fn ($query) => $query->whereIn('provider', AmadeusSelfServiceProvider::aliases()),
                    fn ($query) => $query->where('provider', $provider),
                )
                ->get();
            $module = ServiceModule::query()
                ->where(function ($query) use ($provider): void {
                    if (AmadeusSelfServiceProvider::matches($provider)) {
                        $query->whereIn('provider', AmadeusSelfServiceProvider::aliases())
                            ->orWhereIn('provider_code', AmadeusSelfServiceProvider::aliases());
                    } else {
                        $query->where('provider', $provider)
                            ->orWhere('provider_code', $provider);
                    }
                })
                ->orderByDesc('is_default')
                ->orderByDesc('is_active')
                ->orderBy('provider_priority')
                ->orderBy('sort_order')
                ->first();
            $moduleHealth = $module !== null
                ? ServiceModuleHealthCheck::query()
                    ->where('service_module_id', $module->id)
                    ->latest('checked_at')
                    ->first()
                : null;

            return [
                'provider' => $provider,
                'connections' => $rows->count(),
                'active_connections' => $rows->where('is_active', true)->count(),
                'last_checked_at' => $rows->sortByDesc('last_checked_at')->first()?->last_checked_at,
                'module' => $module,
                'module_status' => (string) ($module?->status ?? 'inactive'),
                'module_environment' => (string) ($module?->environment ?? 'sandbox'),
                'module_health_status' => (string) ($moduleHealth?->result_status ?? 'warning'),
                'supported_operations' => array_values((array) ($module?->supported_operations_json ?: $module?->available_operations ?: [])),
            ];
        })->sortByDesc(static fn (array $row): bool => AmadeusSelfServiceProvider::matches((string) $row['provider']))->values();

        return view('admin.integrations.providers.index', [
            'providers' => $providers,
        ]);
    }
}
