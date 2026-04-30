<?php

namespace App\Http\Requests\Admin;

use App\Models\Inquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInquiryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Inquiry::statuses())],
            'admin_notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
