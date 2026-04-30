<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LegacyRouteController extends Controller
{
    public function packageIndex(Request $request): RedirectResponse
    {
        $mapped = [
            'q' => $request->query('q', $request->query('search', $request->query('keyword'))),
            'destination' => $request->query('destination', $request->query('city')),
            'category' => $request->query('category'),
            'price_min' => $request->query('price_min', $request->query('min_price')),
            'price_max' => $request->query('price_max', $request->query('max_price')),
            'sort' => $request->query('sort'),
            'page' => $request->query('page'),
        ];

        return redirect()->route('frontend.packages.index', array_filter($mapped, fn ($value) => $value !== null && $value !== ''));
    }

    public function groupsByFilter(Request $request): RedirectResponse
    {
        $legacyGroups = $request->query('groups');

        $status = $request->query('status');
        if (! $status && is_string($legacyGroups) && $legacyGroups !== 'all') {
            $status = $legacyGroups;
        }

        $mapped = [
            'q' => $request->query('q', $request->query('search', $request->query('keyword'))),
            'status' => $status,
            'departure_from' => $request->query('departure_from', $request->query('date_from')),
            'departure_to' => $request->query('departure_to', $request->query('date_to')),
            'sort' => $request->query('sort'),
            'page' => $request->query('page'),
        ];

        return redirect()->route('frontend.groups.index', array_filter($mapped, fn ($value) => $value !== null && $value !== ''));
    }
}
