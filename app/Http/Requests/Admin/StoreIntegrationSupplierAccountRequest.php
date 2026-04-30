<?php

namespace App\Http\Requests\Admin;

use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Services\Integrations\IntegrationCredentialValidationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreIntegrationSupplierAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'ownership_type' => ['required', Rule::in(['platform_owner', 'tenant', 'shared_enterprise'])],
            'ownership_tenant_id' => ['nullable', 'integer', 'exists:tenants,id', 'required_if:ownership_type,tenant'],
            'provider' => ['required', 'string', Rule::in(['travelport', 'sabre', AmadeusSelfServiceProvider::CODE, AmadeusSelfServiceProvider::LEGACY_CODE, 'iati', 'duffel'])],

            'test.base_url' => [
                'nullable',
                'url',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || trim($value) === '') {
                        return;
                    }

                    $path = strtolower((string) parse_url($value, PHP_URL_PATH));
                    if ($path !== '' && str_contains($path, '/v2/auth/token')) {
                        $fail('Base URL must not include /v2/auth/token. Enter only the provider host (for example: https://api.cert.platform.sabre.com).');
                    }
                },
            ],
            'test.client_id' => ['nullable', 'string', 'max:255'],
            'test.client_secret' => ['nullable', 'string', 'max:2048'],
            'test.api_token' => ['nullable', 'string', 'max:2048'],
            'test.is_active' => ['sometimes', 'boolean'],

            'production.base_url' => [
                'nullable',
                'url',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || trim($value) === '') {
                        return;
                    }

                    $path = strtolower((string) parse_url($value, PHP_URL_PATH));
                    if ($path !== '' && str_contains($path, '/v2/auth/token')) {
                        $fail('Base URL must not include /v2/auth/token. Enter only the provider host (for example: https://api.cert.platform.sabre.com).');
                    }
                },
            ],
            'production.client_id' => ['nullable', 'string', 'max:255'],
            'production.client_secret' => ['nullable', 'string', 'max:2048'],
            'production.api_token' => ['nullable', 'string', 'max:2048'],
            'production.is_active' => ['sometimes', 'boolean'],

            'default_environment' => ['nullable', Rule::in(['test', 'production'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var IntegrationCredentialValidationService $credentialValidation */
            $credentialValidation = app(IntegrationCredentialValidationService::class);
            $provider = strtolower((string) $this->input('provider', ''));

            foreach (['test', 'production'] as $environment) {
                $block = is_array($this->input($environment, [])) ? $this->input($environment, []) : [];
                $baseUrl = trim((string) ($block['base_url'] ?? ''));
                $isActive = (bool) ($block['is_active'] ?? false);
                $submitted = $credentialValidation->sanitizeForPersistence([
                    'client_id' => $block['client_id'] ?? null,
                    'client_secret' => $block['client_secret'] ?? null,
                    'api_token' => $block['api_token'] ?? null,
                ]);
                $finalCredentials = $this->finalCredentialsForEnvironment($environment, $submitted);
                $hasAnyCredentialInput = $finalCredentials !== [];
                $shouldValidateCompleteness = $isActive || $baseUrl !== '' || $hasAnyCredentialInput;
                if (! $shouldValidateCompleteness) {
                    continue;
                }

                if ($baseUrl === '') {
                    $validator->errors()->add("{$environment}.base_url", ucfirst($environment).' base URL is required when enabling/testing this environment.');
                }

                if ($provider === 'duffel') {
                    $token = trim((string) ($finalCredentials['api_token'] ?? $finalCredentials['client_id'] ?? $finalCredentials['client_secret'] ?? ''));
                    if ($token === '') {
                        $validator->errors()->add("{$environment}.api_token", ucfirst($environment).' Duffel api token is required.');
                    }
                    if ($environment === 'test' && $token !== '' && ! str_starts_with($token, 'duffel_test_')) {
                        $validator->errors()->add("{$environment}.api_token", 'Duffel test api token must start with duffel_test_.');
                    }

                    continue;
                }

                if (trim((string) ($finalCredentials['client_id'] ?? '')) === '') {
                    $validator->errors()->add("{$environment}.client_id", ucfirst($environment).' client ID is required.');
                }
                if (trim((string) ($finalCredentials['client_secret'] ?? '')) === '') {
                    $validator->errors()->add("{$environment}.client_secret", ucfirst($environment).' client secret is required.');
                }
            }
        });
    }

    /**
     * @param  array<string, string>  $submitted
     * @return array<string, string>
     */
    protected function finalCredentialsForEnvironment(string $environment, array $submitted): array
    {
        return $submitted;
    }
}
