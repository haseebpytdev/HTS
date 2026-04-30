<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingDocumentValidationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'validation_status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'is_customer_visible' => ['sometimes', 'boolean'],
            'validation_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
