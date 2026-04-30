<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleCatalogRequest extends FormRequest
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
            'enabled' => ['sometimes', 'boolean'],
            'environment' => ['required', Rule::in(['sandbox', 'production'])],
            'base_currency' => ['required', 'string', 'size:3'],
            'b2b_markup_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'b2b_markup_value' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'b2c_markup_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'b2c_markup_value' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'documentation_url' => ['nullable', 'url', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
