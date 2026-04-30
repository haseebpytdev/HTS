<?php

namespace App\Actions\Inquiry;

use App\Enums\LeadPipelineStage;
use App\Models\Inquiry;
use App\Models\TravelGroup;
use App\Models\TravelPackage;

class CreateLeadInquiryAction
{
    public function execute(array $payload, string $source): Inquiry
    {
        $payload['source'] = $source;
        $payload['children'] = $payload['children'] ?? 0;
        $payload['currency'] = strtoupper($payload['currency'] ?? 'PKR');
        $payload['status'] = Inquiry::STATUS_NEW;
        $payload['pipeline_stage'] = LeadPipelineStage::New->value;

        if (! empty($payload['group_id'])) {
            $group = TravelGroup::query()->with('package')->findOrFail($payload['group_id']);
            $payload['package_id'] = $group->package_id;
            $payload['agency_id'] = $group->agency_id;
            $payload['destination_id'] = $group->package?->destination_id;
        } elseif (! empty($payload['package_id'])) {
            $package = TravelPackage::query()->findOrFail($payload['package_id']);
            $payload['agency_id'] = $package->agency_id;
            $payload['destination_id'] = $package->destination_id;
        }

        return Inquiry::query()->create($payload);
    }
}
