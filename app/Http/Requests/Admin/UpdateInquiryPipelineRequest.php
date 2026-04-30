<?php

namespace App\Http\Requests\Admin;

use App\Enums\LeadPipelineStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInquiryPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('estimated_value') && $this->input('estimated_value') === '') {
            $this->merge(['estimated_value' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'pipeline_stage' => ['required', Rule::enum(LeadPipelineStage::class)],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }
}
