<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Quotation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('access-agency-area');

        $agencyId = $request->user()->agency_id;

        return view('agency.dashboard.index', [
            'stats' => [
                'quotations_total' => Quotation::where('agency_id', $agencyId)->count(),
                'quotations_pending' => Quotation::where('agency_id', $agencyId)->whereIn('status', ['draft', 'sent'])->count(),
                'booked_intents' => Quotation::where('agency_id', $agencyId)->where('status', 'approved')->count(),
                'inquiries_total' => Inquiry::where('agency_id', $agencyId)->count(),
            ],
            'latestQuotations' => Quotation::where('agency_id', $agencyId)->latest('id')->limit(5)->get(),
        ]);
    }
}
