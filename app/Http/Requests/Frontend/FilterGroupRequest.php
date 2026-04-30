<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;

class FilterGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:open,closed,cancelled'],
            'departure_from' => ['nullable', 'date'],
            'departure_to' => ['nullable', 'date', 'after_or_equal:departure_from'],
            'sort' => ['nullable', 'in:latest,earliest_departure,seats_left'],
            'per_page' => ['nullable', 'integer', 'min:6', 'max:48'],
        ];
    }
}
