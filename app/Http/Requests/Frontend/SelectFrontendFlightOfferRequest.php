<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectFrontendFlightOfferRequest extends FormRequest
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
            'correlation_id' => ['required', 'string', 'max:64'],
            'offer_reference' => ['required', 'string', 'max:256'],
            'provider' => ['nullable', 'string', Rule::in(config('integrations.supported_drivers', []))],
        ];
    }
}
