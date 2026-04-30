<?php

namespace App\Http\Requests\Admin;

use App\Enums\LeadPipelineStage;
use App\Models\Inquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(Inquiry::statuses())],
            'pipeline_stage' => ['nullable', Rule::in(LeadPipelineStage::values())],
            'source' => ['nullable', 'string', 'max:50'],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'destination_id' => ['nullable', 'integer', 'exists:destinations,id'],
            'has_quotation' => ['nullable', Rule::in(['0', '1'])],
            'has_open_follow_up' => ['nullable', Rule::in(['0', '1'])],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
        ];
    }
}
