<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchFrontendFlightsRequest extends FormRequest
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
            'trip_type' => ['nullable', Rule::in(['one_way', 'roundtrip', 'multi_city'])],
            'from' => ['required', 'string', 'max:120'],
            'to' => ['required', 'string', 'max:120'],
            'origin' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'destination' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'departure_date' => ['required', 'date_format:Y-m-d'],
            'passengers' => ['required', Rule::in(['1', '2', '3', '4'])],
            'cabin_class' => ['nullable', Rule::in(['economy', 'premium_economy', 'business'])],
            'provider' => ['nullable', 'string', Rule::in(config('integrations.supported_drivers', []))],
            'display_currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $origin = $this->normalizeIataCode((string) $this->input('origin', ''));
        $destination = $this->normalizeIataCode((string) $this->input('destination', ''));

        if ($origin === null) {
            $origin = $this->extractIataCode((string) $this->input('from', ''));
        }
        if ($destination === null) {
            $destination = $this->extractIataCode((string) $this->input('to', ''));
        }

        $merge = [
            'origin' => $origin,
            'destination' => $destination,
        ];
        if ($this->has('display_currency')) {
            $merge['display_currency'] = $this->normalizeCurrencyCode((string) $this->input('display_currency', ''));
        }

        $this->merge($merge);
    }

    private function normalizeCurrencyCode(string $value): ?string
    {
        $code = strtoupper(trim($value));

        return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : null;
    }

    private function normalizeIataCode(string $value): ?string
    {
        $normalized = strtoupper(trim($value));

        return preg_match('/^[A-Z]{3}$/', $normalized) === 1 ? $normalized : null;
    }

    private function extractIataCode(string $value): ?string
    {
        $normalized = trim($value);

        if ((bool) preg_match('/^[A-Za-z]{3}$/', $normalized)) {
            return strtoupper($normalized);
        }

        if ((bool) preg_match('/\(([A-Za-z]{3})\)\s*$/', $normalized, $matches)) {
            return strtoupper((string) $matches[1]);
        }

        return null;
    }
}
