<?php

namespace App\Http\Requests\Admin;

use App\Enums\SupportTicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:20000'],
            'priority' => ['required', Rule::in(SupportTicketPriority::values())],
            'channel' => ['nullable', 'string', 'max:30'],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
