<?php

namespace App\Http\Requests\Admin;

use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Models\IntegrationConnection;
use App\Services\Integrations\IntegrationCredentialValidationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateIntegrationConnectionRequest extends FormRequest
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
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'provider' => ['required', Rule::in(['travelport', 'sabre', AmadeusSelfServiceProvider::CODE, AmadeusSelfServiceProvider::LEGACY_CODE, 'iati', 'duffel'])],
            'environment' => ['required', Rule::in(['sandbox', 'production'])],
            'account_name' => ['required', 'string', 'max:255'],
            'base_url' => ['nullable', 'url', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'supported_operations' => ['nullable', 'array'],
            'supported_operations.*' => [Rule::in(['search', 'pricing', 'booking'])],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'markup_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'markup_value' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'base_currency' => ['nullable', 'string', 'size:3'],
            'documentation_url' => ['nullable', 'url', 'max:1000'],
            'credential_owner' => ['nullable', 'string', 'max:255'],
            'module_notes' => ['nullable', 'string', 'max:1000'],
            'credentials' => ['nullable', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var IntegrationCredentialValidationService $credentialValidation */
            $credentialValidation = app(IntegrationCredentialValidationService::class);
            $provider = (string) $this->input('provider', '');
            $normalizedProvider = strtolower($provider);
            /** @var IntegrationConnection|null $connection */
            $connection = $this->route('integration');
            $submittedCredentials = $this->normalizeProviderCredentials(
                $normalizedProvider,
                $credentialValidation->sanitizeForPersistence((array) $this->input('credentials', []))
            );
            $finalCredentials = $credentialValidation->mergeWithStoredCredentials($connection, $submittedCredentials);

            if (AmadeusSelfServiceProvider::matches($provider)) {
                $hasClientId = $credentialValidation->hasNonEmptyCredential($finalCredentials, 'client_id');
                $hasClientSecret = $credentialValidation->hasNonEmptyCredential($finalCredentials, 'client_secret');

                if (! $hasClientId) {
                    $validator->errors()->add('credentials.client_id', 'Amadeus client_id is required.');
                }
                if (! $hasClientSecret) {
                    $validator->errors()->add('credentials.client_secret', 'Amadeus client_secret is required.');
                }

                return;
            }

            if ($normalizedProvider === 'sabre') {
                $baseUrl = trim((string) $this->input('base_url', (string) $connection?->base_url));
                $isActive = (bool) $this->boolean('is_active', (bool) $connection?->is_active);
                $shouldValidateSabreContract = $isActive || $baseUrl !== '' || $finalCredentials !== [];
                if (! $shouldValidateSabreContract) {
                    return;
                }

                if (! $credentialValidation->hasNonEmptyCredential($finalCredentials, 'client_id')) {
                    $validator->errors()->add('credentials.sabre_user_id', 'Sabre User ID is required for token creation.');
                }
                if (! $credentialValidation->hasNonEmptyCredential($finalCredentials, 'client_secret')) {
                    $validator->errors()->add('credentials.sabre_password', 'Sabre Password is required for token creation.');
                }

                $path = strtolower((string) parse_url($baseUrl, PHP_URL_PATH));
                if ($path !== '' && str_contains($path, '/v2/auth/token')) {
                    $validator->errors()->add('base_url', 'Sabre base URL must be host-only (do not include /v2/auth/token).');
                }

                return;
            }

            if ($normalizedProvider !== 'duffel') {
                return;
            }

            $token = trim((string) ($finalCredentials['api_token'] ?? ''));
            $hasApiToken = $token !== '';
            if (! $hasApiToken) {
                $validator->errors()->add('credentials.api_token', 'Duffel api_token is required.');
            }

            $environment = (string) $this->input('environment', (string) $connection?->environment);
            if ($token !== '' && $environment === 'sandbox' && ! str_starts_with($token, 'duffel_test_')) {
                $validator->errors()->add('credentials.api_token', 'Duffel test api_token must start with duffel_test_.');
            }
        });
    }

    /**
     * @param  array<string, string>  $credentials
     * @return array<string, string>
     */
    private function normalizeProviderCredentials(string $provider, array $credentials): array
    {
        if ($provider !== 'sabre') {
            return $credentials;
        }

        $sabreUserId = trim((string) ($credentials['sabre_user_id'] ?? ''));
        $sabrePassword = trim((string) ($credentials['sabre_password'] ?? ''));
        if ($sabreUserId !== '') {
            $credentials['client_id'] = $sabreUserId;
        }
        if ($sabrePassword !== '') {
            $credentials['client_secret'] = $sabrePassword;
        }

        unset($credentials['sabre_user_id'], $credentials['sabre_password']);

        return $credentials;
    }
}
