<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Support\UmrahQuotationPayloadRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return UmrahQuotationPayloadRules::definition();
    }
}
