<?php

namespace App\Integrations\Shared;

use App\Contracts\Integrations\AuthTokenProviderInterface;
use App\Contracts\Integrations\SupplierJsonHttpClientInterface;
use App\Data\Integrations\SupplierHttpResponse;
use App\Integrations\Shared\Concerns\HandlesJsonRequests;
use App\Integrations\Shared\Concerns\HandlesProviderErrors;
use App\Integrations\Shared\Exceptions\ProviderMappingException;
use App\Integrations\Shared\Exceptions\ProviderValidationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Default JSON HTTP client for GDS/supplier REST endpoints.
 */
final class SupplierJsonHttpClient implements SupplierJsonHttpClientInterface
{
    use HandlesJsonRequests;
    use HandlesProviderErrors;

    public function __construct(
        private readonly AuthTokenProviderInterface $auth,
        private readonly string $providerCode,
        private readonly ?string $baseUrl = null,
    ) {
    }

    public function providerCode(): string
    {
        return $this->providerCode;
    }

    public function baseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function get(string $uri, array $query = [], array $headers = []): SupplierHttpResponse
    {
        return $this->send('GET', $uri, null, $query, $headers);
    }

    public function post(string $uri, array $json = [], array $headers = []): SupplierHttpResponse
    {
        return $this->send('POST', $uri, $json, [], $headers);
    }

    public function put(string $uri, array $json = [], array $headers = []): SupplierHttpResponse
    {
        return $this->send('PUT', $uri, $json, [], $headers);
    }

    public function patch(string $uri, array $json = [], array $headers = []): SupplierHttpResponse
    {
        return $this->send('PATCH', $uri, $json, [], $headers);
    }

    public function delete(string $uri, array $query = [], array $headers = []): SupplierHttpResponse
    {
        return $this->send('DELETE', $uri, null, $query, $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function send(string $method, string $uri, ?array $json, array $query, array $headers): SupplierHttpResponse
    {
        return $this->auth->executeWithAuthRetry(fn (): SupplierHttpResponse => $this->sendOnce($method, $uri, $json, $query, $headers));
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function sendOnce(string $method, string $uri, ?array $json, array $query, array $headers): SupplierHttpResponse
    {
        $url = $this->resolveUrl($uri);
        $client = $this->pendingRequest($headers);

        try {
            $response = match ($method) {
                'GET' => $client->get($this->withQuery($url, $query)),
                'DELETE' => $client->delete($this->withQuery($url, $query)),
                'POST' => $client->asJson()->post($url, $json ?? []),
                'PUT' => $client->asJson()->put($url, $json ?? []),
                'PATCH' => $client->asJson()->patch($url, $json ?? []),
                default => throw new ProviderMappingException('Unsupported HTTP method: '.$method, $this->providerCode),
            };
        } catch (Throwable $e) {
            throw new ProviderMappingException('Supplier HTTP transport failed: '.$e->getMessage(), $this->providerCode, $e);
        }

        $status = $response->status();
        $body = $response->body();
        $headerMap = $this->flattenHeaders($response->headers());

        $this->throwForHttpStatus($this->providerCode, $status, $body);

        if ($status >= 400) {
            throw new ProviderValidationException(
                'Supplier rejected the request (HTTP '.$status.')',
                $this->providerCode,
                ['status_code' => $status, 'body' => $body],
            );
        }

        $decoded = $this->decodeBody($body);

        return new SupplierHttpResponse(
            statusCode: $status,
            rawBody: $body,
            decodedJson: $decoded,
            headers: $headerMap,
        );
    }

    /**
     * @param  array<string, list<string>>|array<string, mixed>  $headers
     * @return array<string, string>
     */
    private function flattenHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $values) {
            if (is_array($values) && isset($values[0])) {
                $out[strtolower((string) $name)] = (string) $values[0];
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(string $body): array
    {
        $trim = trim($body);
        if ($trim === '') {
            return [];
        }

        return $this->decodeJsonResponse($this->providerCode, $body);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function pendingRequest(array $headers): PendingRequest
    {
        $token = $this->auth->getAccessToken();

        $request = Http::timeout($this->httpTimeoutSeconds())
            ->connectTimeout($this->httpConnectTimeoutSeconds())
            ->withHeaders(array_merge([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ], $headers));

        // Temporary debugging toggle only. Keep disabled in normal environments.
        if ($this->shouldDisableSslVerification()) {
            $request = $request->withOptions(['verify' => false]);
        }

        return $request;
    }

    private function httpTimeoutSeconds(): int
    {
        return (int) config('integrations.http_timeout_seconds', 30);
    }

    private function httpConnectTimeoutSeconds(): int
    {
        return (int) config('integrations.http_connect_timeout_seconds', 10);
    }

    private function shouldDisableSslVerification(): bool
    {
        return $this->providerCode === 'duffel'
            && (bool) config('integrations.duffel_debug_disable_ssl_verify', false);
    }

    private function resolveUrl(string $uri): string
    {
        if (Str::startsWith($uri, ['http://', 'https://'])) {
            return $uri;
        }

        $base = $this->baseUrl;
        if ($base === null || $base === '') {
            throw new ProviderMappingException('Supplier base URL is not configured', $this->providerCode);
        }

        return rtrim($base, '/').'/'.ltrim($uri, '/');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function withQuery(string $url, array $query): string
    {
        if ($query === []) {
            return $url;
        }

        $sep = str_contains($url, '?') ? '&' : '?';

        return $url.$sep.http_build_query($query);
    }
}
