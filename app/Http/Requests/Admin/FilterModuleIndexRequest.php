<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterModuleIndexRequest extends FormRequest
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
            'service_type' => ['nullable', Rule::in(['Flights', 'Insurance', 'Stays', 'Cars', 'Tours', 'Visa', 'Umrah'])],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive', 'misconfigured', 'healthy', 'warning', 'critical'])],
        ];
    }
}

