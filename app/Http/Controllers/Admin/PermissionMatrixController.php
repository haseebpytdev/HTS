<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePermissionMatrixRoleRequest;
use App\Services\Auth\PermissionMatrixProposalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PermissionMatrixController extends Controller
{
    public function index(PermissionMatrixProposalService $service): View
    {
        $this->authorize('manage-permission-matrix');

        return view('admin.system.permissions.index', [
            'baseMatrix' => $service->baseMatrix(),
            'draftMatrix' => $service->draftMatrix(),
            'proposedMatrix' => $service->proposedMatrix(),
            'allPermissions' => $service->allKnownPermissions(),
        ]);
    }

    public function edit(string $role, PermissionMatrixProposalService $service): View
    {
        $this->authorize('manage-permission-matrix');
        abort_unless(in_array($role, UserRole::values(), true), 404);

        $base = $service->baseMatrix();
        $draft = $service->draftMatrix();

        return view('admin.system.permissions.edit-role', [
            'role' => $role,
            'currentPermissions' => $draft[$role] ?? ($base[$role] ?? []),
            'allPermissions' => $service->allKnownPermissions(),
            'isProposedOverride' => array_key_exists($role, $service->proposedMatrix()),
        ]);
    }

    public function update(string $role, UpdatePermissionMatrixRoleRequest $request, PermissionMatrixProposalService $service): RedirectResponse
    {
        $this->authorize('manage-permission-matrix');
        abort_unless(in_array($role, UserRole::values(), true), 404);

        /** @var list<string> $permissions */
        $permissions = array_values($request->validated('permissions', []));
        $service->saveRoleProposal($role, $permissions);

        return redirect()
            ->route('admin.system.permissions.index')
            ->with('success', 'Permission proposal saved for role: '.$role);
    }

    public function export(PermissionMatrixProposalService $service): Response
    {
        $this->authorize('manage-permission-matrix');

        return response($service->exportConfigString(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="permissions.role_matrix.proposed.php"',
        ]);
    }
}
