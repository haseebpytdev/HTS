<?php

namespace App\Http\Requests\Admin;

use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterFlightSearchResultRequest extends FormRequest
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
            'provider' => ['nullable', Rule::in(['travelport', 'sabre', AmadeusSelfServiceProvider::CODE, AmadeusSelfServiceProvider::LEGACY_CODE, 'iati', 'duffel'])],
            'status' => ['nullable', Rule::in(['pending', 'completed', 'failed'])],
            'correlation_id' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }
}
