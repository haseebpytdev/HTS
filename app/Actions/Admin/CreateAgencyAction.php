<?php

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\User;
use App\Repositories\AgencyRepository;
use App\Services\Tenancy\TenancySettings;
use Illuminate\Support\Facades\Auth;

class CreateAgencyAction
{
    public function __construct(
        private readonly AgencyRepository $agencyRepository,
        private readonly TenancySettings $tenancySettings,
    ) {
    }

    public function execute(array $validatedData): Agency
    {
        $payload = [
            'name' => $validatedData['name'],
            'code' => strtoupper($validatedData['code']),
            'is_active' => (bool) ($validatedData['is_active'] ?? false),
        ];

        $actor = Auth::user();
        if (
            $this->tenancySettings->tenantScopingEnabled()
            && $actor instanceof User
            && $actor->role !== UserRole::SUPER_ADMIN
            && $actor->tenant_id !== null
        ) {
            $payload['tenant_id'] = $actor->tenant_id;
        }

        return $this->agencyRepository->create($payload);
    }
}
