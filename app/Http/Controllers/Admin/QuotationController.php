<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\DeleteQuotationAction;
use App\Actions\Admin\DuplicateQuotationAction;
use App\Actions\Admin\RecordExportHistoryAction;
use App\Actions\Admin\UpsertQuotationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterQuotationRequest;
use App\Http\Requests\Admin\StoreQuotationRequest;
use App\Http\Requests\Admin\UpdateQuotationRequest;
use App\Models\Agency;
use App\Models\ExportHistory;
use App\Models\Quotation;
use App\Repositories\QuotationRepository;
use App\Services\Quotation\QuotationBuilderFormDataService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationRepository $quotationRepository,
        private readonly UpsertQuotationAction $upsertQuotationAction,
        private readonly DuplicateQuotationAction $duplicateQuotationAction,
        private readonly DeleteQuotationAction $deleteQuotationAction,
        private readonly RecordExportHistoryAction $recordExportHistoryAction,
        private readonly QuotationBuilderFormDataService $quotationBuilderFormDataService
    ) {
    }

    public function index(FilterQuotationRequest $request): View
    {
        $this->authorize('access-admin-area');

        return view('admin.quotations.index', [
            'quotations' => $this->quotationRepository->paginateForAdmin($request->validated()),
            'filters' => $request->validated(),
            'agencies' => Agency::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.quotations.create', $this->quotationBuilderFormDataService->forBuilder());
    }

    public function store(StoreQuotationRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');

        $quotation = $this->upsertQuotationAction->execute($request->validated());

        return redirect()->route('admin.quotations.show', $quotation)
            ->with('success', 'Quotation created successfully.');
    }

    public function show(Quotation $quotation): View
    {
        $this->authorize('access-admin-area');
        $this->authorize('view', $quotation);
        $quotation->load(['agency', 'inquiry', 'items']);

        return view('admin.quotations.show', compact('quotation'));
    }

    public function edit(Quotation $quotation): View
    {
        $this->authorize('access-admin-area');
        $this->authorize('view', $quotation);
        $quotation->load('items');

        return view('admin.quotations.edit', [
            ...$this->quotationBuilderFormDataService->forBuilder(),
            'quotation' => $quotation,
        ]);
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $quotation);

        $quotation = $this->upsertQuotationAction->execute($request->validated(), $quotation);

        return redirect()->route('admin.quotations.show', $quotation)
            ->with('success', 'Quotation updated successfully.');
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $quotation);

        $this->deleteQuotationAction->execute($quotation);

        return redirect()->route('admin.quotations.index')
            ->with('success', 'Quotation deleted successfully.');
    }

    public function duplicate(Quotation $quotation): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $quotation);
        $quotation->load('items');

        $copy = $this->duplicateQuotationAction->execute($quotation);

        return redirect()->route('admin.quotations.edit', $copy)
            ->with('success', 'Quotation duplicated. Review and save changes.');
    }

    public function print(Quotation $quotation): View
    {
        $this->authorize('access-admin-area');
        $this->authorize('view', $quotation);
        $quotation->load(['agency', 'items']);

        return view('admin.quotations.print', [
            'quotation' => $quotation,
            'renderMode' => 'print',
        ]);
    }

    public function exportPdf(Quotation $quotation): Response
    {
        $this->authorize('access-admin-area');
        $this->authorize('view', $quotation);
        $quotation->load(['agency', 'items']);

        $pdf = Pdf::loadView('admin.quotations.print', [
            'quotation' => $quotation,
            'renderMode' => 'pdf',
        ])->setPaper('a4');

        $fileName = "quotation-{$quotation->quote_number}.pdf";
        $this->recordExportHistoryAction->execute(
            (int) auth()->id(),
            ExportHistory::TYPE_QUOTATION_PDF,
            $quotation,
            [
                'file_name' => $fileName,
                'quote_number' => $quotation->quote_number,
            ]
        );

        return $pdf->download($fileName);
    }
}
