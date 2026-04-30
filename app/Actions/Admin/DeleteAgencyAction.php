<?php

namespace App\Actions\Admin;

use App\Models\Agency;
use App\Repositories\AgencyRepository;

class DeleteAgencyAction
{
    public function __construct(private readonly AgencyRepository $agencyRepository)
    {
    }

    public function execute(Agency $agency): void
    {
        $this->agencyRepository->delete($agency);
    }
}
