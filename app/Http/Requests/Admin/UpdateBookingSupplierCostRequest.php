<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingSupplierCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_cost_total' => ['required', 'numeric', 'min:0'],
            'supplier_cost_currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
