<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'environment' => ['required', Rule::in(['development', 'sandbox', 'production'])],
            'is_default_provider' => ['sometimes', 'boolean'],
            'available_operations' => ['nullable', 'array'],
            'available_operations.*' => [Rule::in(['search', 'pricing', 'booking'])],
        ];
    }
}
