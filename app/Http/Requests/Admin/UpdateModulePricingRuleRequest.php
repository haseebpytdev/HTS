<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModulePricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'markup_type_b2b' => ['required', Rule::in(['fixed', 'percentage'])],
            'markup_value_b2b' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'markup_type_b2c' => ['required', Rule::in(['fixed', 'percentage'])],
            'markup_value_b2c' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'base_currency_code' => ['required', 'string', 'size:3'],
        ];
    }
}
