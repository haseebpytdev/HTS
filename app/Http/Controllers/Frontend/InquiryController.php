<?php

namespace App\Http\Controllers\Frontend;

use App\Actions\Inquiry\CreateLeadInquiryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\StoreGroupInquiryRequest;
use App\Http\Requests\Frontend\StorePackageInquiryRequest;
use App\Http\Requests\Frontend\StoreQuoteInquiryRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class InquiryController extends Controller
{
    public function __construct(
        private readonly CreateLeadInquiryAction $createLeadInquiryAction
    ) {
    }

    public function createQuote(): View
    {
        return view('frontend.inquiries.quote');
    }

    public function storeQuote(StoreQuoteInquiryRequest $request): RedirectResponse
    {
        $this->createLeadInquiryAction->execute($request->validated(), 'quote');

        return back()->with('status', 'Quote inquiry submitted successfully. Our team will contact you soon.');
    }

    public function storePackage(StorePackageInquiryRequest $request): RedirectResponse
    {
        $this->createLeadInquiryAction->execute($request->validated(), 'package');

        return back()->with('status', 'Package inquiry submitted successfully.');
    }

    public function storeGroup(StoreGroupInquiryRequest $request): RedirectResponse
    {
        $this->createLeadInquiryAction->execute($request->validated(), 'group');

        return back()->with('status', 'Group inquiry submitted successfully.');
    }
}
