<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleTaxRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tax_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'tax_value' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'currency_id' => ['nullable', 'integer'],
        ];
    }
}
