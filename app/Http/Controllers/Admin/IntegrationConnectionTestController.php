<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\TestIntegrationConnectionAction;
use App\Http\Controllers\Controller;
use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Models\IntegrationConnection;
use App\Services\Integrations\IntegrationConnectionAdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class IntegrationConnectionTestController extends Controller
{
    public function __invoke(
        IntegrationConnection $integration,
        TestIntegrationConnectionAction $action,
        IntegrationConnectionAdminAuditService $adminAudit,
    ): RedirectResponse {
        $integration = $integration->fresh(['credentials']) ?? $integration->load('credentials');
        $result = $action->execute($integration);
        $integration->refresh();

        $adminAudit->logConnectionAction($integration, 'tested', Auth::id(), [
            'test_ok' => $result['ok'],
            'message' => $result['message'],
        ]);

        $flashType = $result['ok'] ? 'success' : 'error';
        $providerLabel = AmadeusSelfServiceProvider::matches((string) $integration->provider)
            ? 'AMADEUS SELF SERVICE'
            : strtoupper((string) $integration->provider);
        $environmentLabel = match ((string) $integration->environment) {
            'sandbox' => 'TEST',
            'production' => 'LIVE',
            default => strtoupper((string) $integration->environment),
        };
        $message = $providerLabel.' '.$environmentLabel.' test: '.$result['message'];

        return back()->with($flashType, $message);
    }
}
