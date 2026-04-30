<?php

namespace App\Actions\Admin;

use App\Models\Agency;
use App\Repositories\AgencyRepository;

class UpdateAgencyAction
{
    public function __construct(private readonly AgencyRepository $agencyRepository)
    {
    }

    public function execute(Agency $agency, array $validatedData): Agency
    {
        $payload = [
            'name' => $validatedData['name'],
            'code' => strtoupper($validatedData['code']),
            'is_active' => (bool) ($validatedData['is_active'] ?? false),
        ];

        return $this->agencyRepository->update($agency, $payload);
    }
}
