<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConvertInquiryToBookingIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quotation_id' => ['required', 'integer', 'exists:quotations,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
