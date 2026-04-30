<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerSavedTravelerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('customer')->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['nullable', 'date'],
            'passport_no' => ['nullable', 'string', 'max:64'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'traveler_type' => ['required', 'string', 'in:adult,child,infant'],
        ];
    }
}
