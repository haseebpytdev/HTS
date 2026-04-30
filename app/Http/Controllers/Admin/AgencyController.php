<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateAgencyAction;
use App\Actions\Admin\DeleteAgencyAction;
use App\Actions\Admin\UpdateAgencyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterAgencyRequest;
use App\Http\Requests\Admin\StoreAgencyRequest;
use App\Http\Requests\Admin\UpdateAgencyRequest;
use App\Models\Agency;
use App\Repositories\AgencyRepository;
use App\Services\Payment\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AgencyController extends Controller
{
    public function __construct(
        private readonly AgencyRepository $agencyRepository,
        private readonly CreateAgencyAction $createAgencyAction,
        private readonly UpdateAgencyAction $updateAgencyAction,
        private readonly DeleteAgencyAction $deleteAgencyAction,
        private readonly LedgerService $ledgerService,
    ) {
    }

    public function index(FilterAgencyRequest $request): View
    {
        $this->authorize('access-admin-area');

        $agencies = $this->agencyRepository->paginateForAdmin($request->validated());

        return view('admin.agencies.index', [
            'agencies' => $agencies,
            'filters' => $request->validated(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.agencies.create');
    }

    public function store(StoreAgencyRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('create', Agency::class);

        $this->createAgencyAction->execute($request->validated());

        return redirect()->route('admin.agencies.index')
            ->with('success', 'Agency created successfully.');
    }

    public function edit(Agency $agency): View
    {
        $this->authorize('access-admin-area');
        $this->authorize('view', $agency);

        $wallet = $this->ledgerService->ensureWallet($agency);

        return view('admin.agencies.edit', compact('agency', 'wallet'));
    }

    public function update(UpdateAgencyRequest $request, Agency $agency): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $agency);

        $this->updateAgencyAction->execute($agency, $request->validated());

        return redirect()->route('admin.agencies.index')
            ->with('success', 'Agency updated successfully.');
    }

    public function destroy(Agency $agency): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('delete', $agency);

        $this->deleteAgencyAction->execute($agency);

        return redirect()->route('admin.agencies.index')
            ->with('success', 'Agency deleted successfully.');
    }
}
