<?php

namespace App\Console\Commands;

use App\Integrations\Sabre\SabreAuthService;
use App\Services\Integrations\ProviderCredentialResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class DebugSabreTokenCommand extends Command
{
    protected $signature = 'debug:sabre-token
        {--environment=sandbox : Runtime environment (sandbox|production)}
        {--timeout=30 : HTTP timeout seconds}';

    protected $description = 'Run safe Sabre token diagnostics without exposing secrets';

    public function handle(
        ProviderCredentialResolver $resolver,
        SabreAuthService $sabreAuth
    ): int {
        $environment = strtolower(trim((string) $this->option('environment')));
        $environment = in_array($environment, ['sandbox', 'production'], true) ? $environment : 'sandbox';
        $correlationId = (string) Str::uuid();
        $timeout = max(5, (int) $this->option('timeout'));

        $resolved = $resolver->forProvider(
            providerCode: 'sabre',
            operation: 'search',
            runtimeEnvironmentOverride: $environment,
        );
        $baseUrl = trim((string) ($resolved->baseUrl ?? ''));
        $tokenPath = $sabreAuth->tokenPath();
        $url = $baseUrl !== '' ? rtrim($baseUrl, '/').$tokenPath : $tokenPath;
        $hasClientId = trim($resolved->clientId) !== '';
        $hasClientSecret = trim($resolved->clientSecret) !== '';
        $connectionId = $resolved->integrationConnectionId;
        $source = $resolved->credentialSource;

        $this->line('provider=sabre');
        $this->line('environment='.$environment);
        $this->line('base_url='.($baseUrl !== '' ? $baseUrl : 'missing'));
        $this->line('token_path='.$tokenPath);
        $this->line('connection_id='.($connectionId !== null ? (string) $connectionId : 'null'));
        $this->line('credential_source='.$source);
        $this->line('has_client_id='.($hasClientId ? 'true' : 'false'));
        $this->line('has_client_secret='.($hasClientSecret ? 'true' : 'false'));
        $this->line('correlation_id='.$correlationId);

        if ($baseUrl === '' || ! $hasClientId || ! $hasClientSecret) {
            $this->line('http_status=not_sent');
            $this->line('curl_error/exception=preflight_failed_missing_base_or_credentials');

            return self::FAILURE;
        }

        $startedAt = microtime(true);

        try {
            $tokenExchange = $sabreAuth->executeTokenRequest(
                baseUrl: $baseUrl,
                clientId: $resolved->clientId,
                clientSecret: $resolved->clientSecret,
                correlationId: $correlationId,
                timeoutSeconds: $timeout,
            );
            $response = $tokenExchange['response'];

            $latencyMs = (int) $tokenExchange['latency_ms'];
            $sanitizedBody = $this->sanitizeBody((string) $response->body());

            $this->line('http_status='.(string) $response->status());
            $this->line('latency_ms='.(string) $latencyMs);
            $this->line('response_body='.$sanitizedBody);
            $this->line('curl_error/exception=none');

            return $response->successful() ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            $this->line('http_status=transport_error');
            $this->line('latency_ms='.(string) $latencyMs);
            $this->line('curl_error/exception='.$e::class.': '.$this->sanitizeText($e->getMessage()));

            return self::FAILURE;
        }
    }

    private function sanitizeBody(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return 'empty';
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            unset(
                $decoded['access_token'],
                $decoded['refresh_token'],
                $decoded['id_token'],
                $decoded['token']
            );

            return $this->truncate((string) json_encode($decoded, JSON_UNESCAPED_SLASHES));
        }

        return $this->truncate($this->sanitizeText($trimmed));
    }

    private function sanitizeText(string $text): string
    {
        $sanitized = preg_replace('/(access_token|refresh_token|id_token|token)\s*[:=]\s*["\']?[^"\',\s]+/i', '$1=[redacted]', $text);

        return $this->truncate((string) $sanitized);
    }

    private function truncate(string $value, int $limit = 1200): string
    {
        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit).'...[truncated]' : $value;
    }
}
