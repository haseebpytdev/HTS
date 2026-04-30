<?php

namespace App\Http\Requests\Admin;

use App\Models\IntegrationConnection;
use App\Services\Integrations\IntegrationCredentialValidationService;

class UpdateIntegrationSupplierAccountRequest extends StoreIntegrationSupplierAccountRequest
{
    /**
     * @param  array<string, string>  $submitted
     * @return array<string, string>
     */
    protected function finalCredentialsForEnvironment(string $environment, array $submitted): array
    {
        $accountKey = (string) $this->route('accountKey');
        if ($accountKey === '') {
            return $submitted;
        }

        $connection = IntegrationConnection::query()
            ->with('credentials')
            ->where('account_key', $accountKey)
            ->where('environment', $environment)
            ->first();
        if ($connection === null) {
            return $submitted;
        }

        /** @var IntegrationCredentialValidationService $credentialValidation */
        $credentialValidation = app(IntegrationCredentialValidationService::class);

        return $credentialValidation->mergeWithStoredCredentials($connection, $submitted);
    }
}
