<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreInquiryApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'in:quote,package,group'],
            'package_id' => ['exclude_unless:source,package', 'required', 'integer', 'exists:packages,id'],
            'group_id' => ['exclude_unless:source,group', 'required', 'integer', 'exists:groups,id'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'travel_date' => ['nullable', 'date'],
            'adults' => ['required', 'integer', 'min:1', 'max:50'],
            'children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'message' => ['required_if:source,quote', 'nullable', 'string', 'max:3000'],
        ];
    }
}
