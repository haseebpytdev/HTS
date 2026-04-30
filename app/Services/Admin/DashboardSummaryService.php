<?php

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Services\Analytics\AdminAnalyticsService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class DashboardSummaryService
{
    public function __construct(
        private readonly AdminAnalyticsService $analytics,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $range = $this->analytics->resolveDateRange(null, null);
        /** @var User|null $user */
        $user = Auth::user();
        $overview = $this->analytics->dashboardOverview();

        return [
            'range' => $range,
            'kpis' => $this->analytics->dashboardKpis($range['from_date'], $range['to_date']),
            'trends' => $this->analytics->trends($range['from_date'], $range['to_date']),
            'overview' => $overview,
            'action_queue' => $overview['action_queue'] ?? [],
            'recent' => $overview['recent'] ?? [],
            'quick_actions' => $this->quickActionsForUser($user),
            'widget_visibility' => $this->widgetVisibilityForUser($user),
        ];
    }

    /**
     * @return array<int, array{label: string, route: string, tone: string}>
     */
    private function quickActionsForUser(?User $user): array
    {
        $isSuperAdmin = $user?->role === UserRole::SUPER_ADMIN;

        $actions = [
            ['label' => 'Open modules', 'route' => 'admin.module-management.index', 'permission' => 'module.integrations.view', 'super_admin_only' => true, 'tone' => 'outline-primary'],
            ['label' => 'Run provider test', 'route' => 'admin.integrations.accounts.index', 'permission' => 'module.integrations.view', 'super_admin_only' => true, 'tone' => 'outline-primary'],
            ['label' => 'Review approvals', 'route' => 'admin.compliance.dashboard', 'permission' => 'module.compliance.view', 'super_admin_only' => false, 'tone' => 'outline-warning'],
            ['label' => 'Review support', 'route' => 'admin.support-tickets.index', 'permission' => 'module.support.view', 'super_admin_only' => false, 'tone' => 'outline-secondary'],
            ['label' => 'View failed tasks', 'route' => 'admin.operations-center.failed-tasks', 'permission' => 'module.dashboard.view', 'super_admin_only' => true, 'tone' => 'outline-danger'],
            ['label' => 'Manage tenants', 'route' => 'admin.system.tenancy.edit', 'permission' => 'manage-tenancy-settings', 'super_admin_only' => true, 'tone' => 'outline-dark'],
            ['label' => 'Manage pricing rules', 'route' => 'admin.settings.section', 'route_params' => ['section' => 'tax_pricing'], 'permission' => 'manage-tenancy-settings', 'super_admin_only' => true, 'tone' => 'outline-success'],
        ];

        $visible = [];
        foreach ($actions as $action) {
            $routeName = (string) $action['route'];
            if (! Route::has($routeName)) {
                continue;
            }
            if ($action['super_admin_only'] && ! $isSuperAdmin) {
                continue;
            }
            if (! $isSuperAdmin && $user !== null && ! $user->hasPermission((string) $action['permission'])) {
                continue;
            }

            $routeParams = is_array($action['route_params'] ?? null) ? $action['route_params'] : [];
            $visible[] = [
                'label' => (string) $action['label'],
                'route' => route($routeName, $routeParams),
                'tone' => (string) $action['tone'],
            ];
        }

        return $visible;
    }

    /**
     * @return array<string, bool>
     */
    private function widgetVisibilityForUser(?User $user): array
    {
        $isSuperAdmin = $user?->role === UserRole::SUPER_ADMIN;
        $can = static fn (?User $u, string $permission): bool => $u !== null && ($u->hasPermission($permission) || in_array('*', $u->permissions(), true));

        return [
            'show_executive_kpis' => $isSuperAdmin || $can($user, 'module.dashboard.view'),
            'show_action_queue' => $isSuperAdmin || $can($user, 'module.bookings.view'),
            'show_live_management' => $isSuperAdmin || $can($user, 'module.integrations.view'),
            'show_performance' => $isSuperAdmin || $can($user, 'module.analytics.view'),
            'show_recent_bookings' => $isSuperAdmin || $can($user, 'module.bookings.view'),
            'show_recent_support' => $isSuperAdmin || $can($user, 'module.support.view'),
            'show_recent_integrations' => $isSuperAdmin || $can($user, 'module.integrations.view'),
            'show_recent_exports' => $isSuperAdmin || $can($user, 'module.analytics.view'),
            'show_recent_activity' => $isSuperAdmin || $can($user, 'module.dashboard.view'),
            'show_health_center' => $isSuperAdmin || $can($user, 'module.integrations.view'),
            'show_cms_links' => $isSuperAdmin || $can($user, 'module.marketing.view'),
            'show_quick_controls' => $isSuperAdmin || $can($user, 'module.dashboard.view'),
        ];
    }
}
