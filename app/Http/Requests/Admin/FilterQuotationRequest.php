<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FilterQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'agency_id' => ['nullable', 'exists:agencies,id'],
            'status' => ['nullable', 'in:draft,sent,approved,rejected'],
        ];
    }
}
