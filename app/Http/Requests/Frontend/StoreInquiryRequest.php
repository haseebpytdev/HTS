<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;

class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['nullable', 'string', 'max:50'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'destination_id' => ['nullable', 'integer', 'exists:destinations,id'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'travel_date' => ['nullable', 'date'],
            'adults' => ['required', 'integer', 'min:1', 'max:50'],
            'children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'message' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
