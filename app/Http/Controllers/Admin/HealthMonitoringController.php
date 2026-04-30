<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Admin\HealthMonitoringService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HealthMonitoringController extends Controller
{
    public function __construct(
        private readonly HealthMonitoringService $monitoring,
    ) {
    }

    public function overview(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.monitoring.health-overview', [
            'overview' => $this->monitoring->healthOverview(),
        ]);
    }

    public function integrationLogs(Request $request): View
    {
        $this->authorize('access-admin-area');
        $provider = $request->string('provider')->toString();
        $logs = $this->monitoring->integrationLogsBrowser($provider !== '' ? $provider : null);

        $user = $request->user();
        $canViewSensitivePayload = $user?->role === UserRole::SUPER_ADMIN->value;

        return view('admin.monitoring.integration-logs-browser', [
            'logs' => $logs,
            'provider' => $provider,
            'canViewSensitivePayload' => $canViewSensitivePayload,
        ]);
    }

    public function asyncTasks(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.monitoring.async-task-monitor', [
            'async' => $this->monitoring->asyncTaskMonitor(),
        ]);
    }

    public function failedAlerts(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.monitoring.failed-jobs-alerts-board', [
            'board' => $this->monitoring->failedJobsAlertsBoard(),
        ]);
    }
}
