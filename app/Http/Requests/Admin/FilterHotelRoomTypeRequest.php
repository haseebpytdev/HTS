<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterHotelRoomTypeRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:255'],
            'hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
            'sharing_basis' => ['nullable', Rule::in(['single', 'double', 'triple', 'quad'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
