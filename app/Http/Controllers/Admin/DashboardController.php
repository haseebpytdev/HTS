<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardHealthService;
use App\Services\Admin\DashboardSummaryService;
use Illuminate\Contracts\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardSummaryService $summary,
        private readonly DashboardHealthService $health,
    ) {
    }

    public function index(): View
    {
        $this->authorize('access-admin-area');

        $summary = $this->safe(fn (): array => $this->summary->summary(), [
            'range' => ['from_date' => null, 'to_date' => null],
            'kpis' => [],
            'trends' => [],
            'overview' => [],
            'quick_actions' => [],
            'widget_visibility' => [],
        ]);

        return view('admin.dashboard.index', [
            'range' => $summary['range'],
            'kpis' => $summary['kpis'],
            'trends' => $summary['trends'],
            'overview' => $summary['overview'],
            'executiveKpis' => $this->safe(fn (): array => $this->health->executiveKpis(), []),
            'actionQueue' => $this->safe(fn (): array => $this->health->actionQueue(), []),
            'healthCenter' => $this->safe(fn (): array => $this->health->healthCenter(), []),
            'recentActivity' => $this->safe(fn (): array => $this->health->recentActivity(), []),
            'quickActions' => $summary['quick_actions'] ?? [],
            'widgetVisibility' => $summary['widget_visibility'] ?? [],
        ]);
    }

    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @param  T  $fallback
     * @return T
     */
    private function safe(callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            report($exception);

            return $fallback;
        }
    }
}
