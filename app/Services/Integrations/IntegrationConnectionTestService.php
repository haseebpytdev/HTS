<?php

namespace App\Services\Integrations;

use App\Integrations\AmadeusSelfService\Support\AmadeusSelfServiceProvider;
use App\Models\IntegrationConnection;
use App\Models\IntegrationCredential;
use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class IntegrationConnectionTestService
{
    /**
     * @return array{ok: bool, message: string, http_status: int|null}
     */
    public function test(IntegrationConnection $connection): array
    {
        $checkedAt = now();
        $correlationId = Str::uuid()->toString();

        if (strtolower((string) $connection->provider) === 'duffel') {
            return $this->testDuffelConnection($connection, $correlationId, $checkedAt);
        }

        $clientId = $this->secret($connection, 'client_id');
        $clientSecret = $this->secret($connection, 'client_secret');
        if ($clientId === null || $clientSecret === null || blank($connection->base_url)) {
            return $this->record(
                connection: $connection,
                correlationId: $correlationId,
                checkedAt: $checkedAt,
                ok: false,
                message: 'Connection config incomplete: base URL / client credentials required.',
                httpStatus: null
            );
        }

        $tokenPath = $this->tokenPath($connection->provider);
        $tokenUrl = rtrim((string) $connection->base_url, '/').$tokenPath;

        try {
            $response = Http::asForm()
                ->timeout((int) config('integrations.http_timeout_seconds', 30))
                ->post($tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);
        } catch (Throwable $e) {
            return $this->record(
                connection: $connection,
                correlationId: $correlationId,
                checkedAt: $checkedAt,
                ok: false,
                message: 'Connection test failed: '.$e->getMessage(),
                httpStatus: null
            );
        }

        if ($response->failed()) {
            return $this->record(
                connection: $connection,
                correlationId: $correlationId,
                checkedAt: $checkedAt,
                ok: false,
                message: 'Token endpoint failed: HTTP '.$response->status(),
                httpStatus: $response->status()
            );
        }

        /** @var array<string, mixed>|null $json */
        $json = $response->json();
        if (! is_array($json) || ! isset($json['access_token'])) {
            return $this->record(
                connection: $connection,
                correlationId: $correlationId,
                checkedAt: $checkedAt,
                ok: false,
                message: 'Token endpoint succeeded but access_token was missing.',
                httpStatus: $response->status()
            );
        }

        return $this->record(
            connection: $connection,
            correlationId: $correlationId,
            checkedAt: $checkedAt,
            ok: true,
            message: 'Connection test passed.',
            httpStatus: $response->status()
        );
    }

    private function secret(IntegrationConnection $connection, string $key): ?string
    {
        $credential = IntegrationCredential::query()
            ->where('integration_connection_id', $connection->id)
            ->where(function ($query) use ($key): void {
                $query->where('key_name', $key)->orWhere('credential_key', $key);
            })
            ->first();

        if ($credential === null) {
            return null;
        }

        $value = trim((string) $credential->credential_value_encrypted);

        return $value !== '' ? $value : null;
    }

    private function tokenPath(string $provider): string
    {
        return match ($provider) {
            'travelport' => (string) config('travelport.token_path', '/oauth/token'),
            'sabre' => (string) config('sabre.token_path', '/v2/auth/token'),
            AmadeusSelfServiceProvider::CODE, AmadeusSelfServiceProvider::LEGACY_CODE => (string) config('amadeus.token_path', '/v1/security/oauth2/token'),
            'iati' => (string) config('iati.token_path', '/oauth/token'),
            'duffel' => (string) config('duffel.token_path', '/oauth/token'),
            default => '/oauth/token',
        };
    }

    /**
     * @return array{ok: bool, message: string, http_status: int|null}
     */
    private function record(IntegrationConnection $connection, string $correlationId, \Illuminate\Support\Carbon $checkedAt, bool $ok, string $message, ?int $httpStatus): array
    {
        $connection->forceFill([
            'last_checked_at' => $checkedAt,
            'last_tested_status' => $ok ? 'ok' : 'failed',
            'last_success_at' => $ok ? $checkedAt : $connection->last_success_at,
            'last_failure_reason' => $ok ? null : mb_substr($message, 0, 2000),
        ])->save();

        IntegrationLog::query()->create([
            'provider' => $connection->provider,
            'correlation_id' => $correlationId,
            'log_type' => $ok ? 'connection_test_success' : 'connection_test_failed',
            'payload' => [
                'integration_connection_id' => $connection->id,
                'environment' => $connection->environment,
                'message' => $message,
                'http_status' => $httpStatus,
            ],
            'created_at' => $checkedAt,
        ]);

        return [
            'ok' => $ok,
            'message' => $message,
            'http_status' => $httpStatus,
        ];
    }

    /**
     * @return array{ok: bool, message: string, http_status: int|null}
     */
    private function testDuffelConnection(IntegrationConnection $connection, string $correlationId, \Illuminate\Support\Carbon $checkedAt): array
    {
        $apiToken = $this->secret($connection, 'api_token')
            ?? $this->secret($connection, 'api_key')
            ?? $this->secret($connection, 'client_id')
            ?? $this->secret($connection, 'client_secret');
        if ($apiToken === null || blank($connection->base_url)) {
            return $this->record(
                connection: $connection,
                correlationId: $correlationId,
                checkedAt: $checkedAt,
                ok: false,
                message: 'Connection config incomplete: Duffel base URL and api token are required.',
                httpStatus: null
            );
        }

        $environment = strtolower((string) ($connection->environment ?: 'test'));
        if (in_array($environment, ['sandbox', 'test', 'testing'], true) && ! str_starts_with($apiToken, 'duffel_test_')) {
            return $this->record(
                connection: $connection,
                correlationId: $correlationId,
                checkedAt: $checkedAt,
                ok: false,
                message: 'Duffel test environment requires an api token starting with duffel_test_.',
                httpStatus: null
            );
        }

        $healthPath = (string) config('duffel.health_check_path', '/air/airlines?limit=1');
        $url = rtrim((string) $connection->base_url, '/').'/'.ltrim($healthPath, '/');
        try {
            $response = Http::withToken($apiToken)
                ->acceptJson()
                ->withHeader('Duffel-Version', (string) config('duffel.version', 'v2'))
                ->timeout((int) config('integrations.http_timeout_seconds', 30))
                ->get($url);
        } catch (Throwable $e) {
            return $this->record(
                connection: $connection,
                correlationId: $correlationId,
                checkedAt: $checkedAt,
                ok: false,
                message: 'Connection test failed: '.$e->getMessage(),
                httpStatus: null
            );
        }

        if ($response->failed()) {
            return $this->record(
                connection: $connection,
                correlationId: $correlationId,
                checkedAt: $checkedAt,
                ok: false,
                message: 'Duffel health endpoint failed: HTTP '.$response->status(),
                httpStatus: $response->status()
            );
        }

        return $this->record(
            connection: $connection,
            correlationId: $correlationId,
            checkedAt: $checkedAt,
            ok: true,
            message: 'Connection test passed.',
            httpStatus: $response->status()
        );
    }
}
