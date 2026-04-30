<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $types = app(\App\Services\Compliance\ApprovalWorkflowService::class)->supportedRequestTypes();

        return [
            'request_type' => ['required', 'string', 'max:80', Rule::in($types)],
            'reference_type' => ['nullable', 'string', 'max:120'],
            'reference_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'payload' => ['nullable', 'array'],
            'payload.reference_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
