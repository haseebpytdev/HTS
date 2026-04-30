<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterFlightSearchResultRequest;
use App\Models\SupplierSearchSession;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class FlightSearchResultController extends Controller
{
    public function index(FilterFlightSearchResultRequest $request): View
    {
        $filters = $request->validated();
        $perPage = 20;

        if (! Schema::hasTable('supplier_search_sessions')) {
            return view('admin.integrations.search-results.index', [
                'sessions' => new LengthAwarePaginator([], 0, $perPage),
                'filters' => $filters,
            ]);
        }

        $query = SupplierSearchSession::query()
            ->withCount('offerSnapshots')
            ->latest('id');

        if (! empty($filters['provider'])) {
            $query->where('provider', (string) $filters['provider']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }
        if (! empty($filters['correlation_id'])) {
            $query->where('correlation_id', 'like', '%'.(string) $filters['correlation_id'].'%');
        }
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', (string) $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', (string) $filters['to']);
        }

        return view('admin.integrations.search-results.index', [
            'sessions' => $query->paginate($perPage)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function show(SupplierSearchSession $searchSession): View
    {
        $searchSession->load(['offerSnapshots', 'connection']);

        return view('admin.integrations.search-results.show', [
            'session' => $searchSession,
        ]);
    }
}
