<?php

namespace App\Integrations\AmadeusSelfService;

use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Models\IntegrationConnection;
use Illuminate\Support\Facades\Http;
use Throwable;

final class AmadeusSelfServiceConnectionTester
{
    /**
     * @param  array<string, string>  $credentials
     * @return array{ok: bool, message: string, token_expires_at: ?string}
     */
    public function test(IntegrationConnection $connection, array $credentials, bool $stubMode): array
    {
        if (! AmadeusSelfServiceProvider::matches((string) $connection->provider)) {
            return [
                'ok' => false,
                'message' => 'Unsupported provider for Amadeus Self Service tester.',
                'token_expires_at' => null,
            ];
        }

        if ($stubMode) {
            return [
                'ok' => true,
                'message' => 'Stub test mode: simulated Amadeus Self Service OAuth success.',
                'token_expires_at' => now()->addHour()->toDateTimeString(),
            ];
        }

        if (blank($credentials['client_id'] ?? null) || blank($credentials['client_secret'] ?? null)) {
            return [
                'ok' => false,
                'message' => 'Amadeus Self Service credentials missing: client_id and client_secret are required.',
                'token_expires_at' => null,
            ];
        }

        $baseUrl = trim((string) ($connection->base_url ?? config('amadeus.base_url', '')));
        if ($baseUrl === '') {
            return [
                'ok' => false,
                'message' => 'Amadeus Self Service base URL is missing for this connection.',
                'token_expires_at' => null,
            ];
        }

        $tokenPath = (string) config('amadeus.token_path', '/v1/security/oauth2/token');
        $url = rtrim($baseUrl, '/').'/'.ltrim($tokenPath, '/');

        try {
            $response = Http::asForm()
                ->timeout((int) config('integrations.http_timeout_seconds', 30))
                ->post($url, [
                    'grant_type' => 'client_credentials',
                    'client_id' => (string) $credentials['client_id'],
                    'client_secret' => (string) $credentials['client_secret'],
                ]);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => mb_substr(trim($e->getMessage()) ?: 'Connection test failed due to transport error.', 0, 2000),
                'token_expires_at' => null,
            ];
        }

        if ($response->failed()) {
            return [
                'ok' => false,
                'message' => 'Amadeus Self Service token HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 1000),
                'token_expires_at' => null,
            ];
        }

        /** @var array<string, mixed>|null $json */
        $json = $response->json();
        $accessToken = is_array($json) ? (string) ($json['access_token'] ?? '') : '';
        if ($accessToken === '') {
            return [
                'ok' => false,
                'message' => 'Amadeus Self Service token response missing access_token.',
                'token_expires_at' => null,
            ];
        }

        $expiresIn = is_array($json) ? (int) ($json['expires_in'] ?? 1800) : 1800;

        return [
            'ok' => true,
            'message' => 'Amadeus Self Service OAuth token generated successfully.',
            'token_expires_at' => now()->addSeconds(max(1, $expiresIn))->toDateTimeString(),
        ];
    }
}

