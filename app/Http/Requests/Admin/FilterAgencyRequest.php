<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FilterAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'in:0,1'],
            'sort' => ['nullable', 'in:id,name,code,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ];
    }
}
