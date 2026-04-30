<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Support\UmrahQuotationPayloadRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationApiRequest extends FormRequest
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
