<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreModuleCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credentials' => ['required', 'array'],
            'credentials.sandbox' => ['nullable', 'array'],
            'credentials.production' => ['nullable', 'array'],
            'credentials.sandbox.*' => ['nullable', 'string', 'max:2048'],
            'credentials.production.*' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
