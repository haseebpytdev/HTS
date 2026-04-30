<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('access-agency-area');

        $inquiries = Inquiry::query()
            ->where('agency_id', $request->user()->agency_id)
            ->latest('id')
            ->paginate(15);

        return view('agency.inquiries.index', compact('inquiries'));
    }
}
