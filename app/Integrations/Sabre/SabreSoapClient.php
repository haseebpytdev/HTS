<?php

namespace App\Integrations\Sabre;

use App\Contracts\Integrations\AuthTokenProviderInterface;
use App\Data\Integrations\SupplierHttpResponse;
use App\Integrations\Shared\Concerns\HandlesProviderErrors;
use App\Integrations\Shared\Exceptions\ProviderAuthException;
use App\Integrations\Shared\Exceptions\ProviderMappingException;
use App\Integrations\Shared\Exceptions\ProviderValidationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class SabreSoapClient
{
    use HandlesProviderErrors;

    public function __construct(
        private readonly AuthTokenProviderInterface $auth,
        private readonly ?string $baseUrl = null,
    ) {
    }

    public function baseUrl(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function request(string $uri, string $soapAction, string $xmlBody, array $headers = []): SupplierHttpResponse
    {
        return $this->auth->executeWithAuthRetry(fn (): SupplierHttpResponse => $this->requestOnce($uri, $soapAction, $xmlBody, $headers));
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function requestOnce(string $uri, string $soapAction, string $xmlBody, array $headers = []): SupplierHttpResponse
    {
        $url = $this->resolveUrl($uri);
        $client = $this->pendingRequest($soapAction, $headers);

        try {
            $response = $client->withBody($xmlBody, 'text/xml; charset=utf-8')->post($url);
        } catch (Throwable $e) {
            throw new ProviderMappingException('Sabre SOAP transport failed: '.$e->getMessage(), 'sabre', $e);
        }

        $status = $response->status();
        $body = $response->body();
        $this->throwForHttpStatus('sabre', $status, $body);
        if ($status >= 400) {
            throw new ProviderMappingException('Sabre SOAP request rejected (HTTP '.$status.')', 'sabre');
        }
        $decoded = $this->decodeXmlToArray($body);
        $this->throwIfSoapFault($decoded);

        return new SupplierHttpResponse(
            statusCode: $status,
            rawBody: $body,
            decodedJson: $decoded,
            headers: $this->flattenHeaders($response->headers()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeXmlToArray(string $xml): array
    {
        $trim = trim($xml);
        if ($trim === '') {
            return [];
        }

        try {
            $element = simplexml_load_string($trim);
        } catch (Throwable $e) {
            throw new ProviderMappingException('Sabre SOAP response is not valid XML', 'sabre', $e);
        }

        if ($element === false) {
            throw new ProviderMappingException('Sabre SOAP response is not valid XML', 'sabre');
        }

        /** @var mixed $decoded */
        $decoded = json_decode((string) json_encode($element), true);

        return is_array($decoded) ? $decoded : ['raw' => $trim];
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
     * @param  array<string, string>  $headers
     */
    private function pendingRequest(string $soapAction, array $headers): PendingRequest
    {
        return Http::timeout((int) config('integrations.http_timeout_seconds', 30))
            ->withHeaders(array_merge([
                'Accept' => 'text/xml, application/xml',
                'Authorization' => 'Bearer '.$this->auth->getAccessToken(),
                'SOAPAction' => $soapAction,
            ], $headers));
    }

    private function resolveUrl(string $uri): string
    {
        if (Str::startsWith($uri, ['http://', 'https://'])) {
            return $uri;
        }
        if ($this->baseUrl === null || $this->baseUrl === '') {
            throw new ProviderMappingException('Sabre SOAP base URL is not configured', 'sabre');
        }

        return rtrim($this->baseUrl, '/').'/'.ltrim($uri, '/');
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function throwIfSoapFault(array $decoded): void
    {
        $fault = $this->findNodeByKey($decoded, 'fault');
        if (! is_array($fault)) {
            return;
        }

        $faultCode = strtolower((string) ($fault['faultcode'] ?? $fault['Code']['Value'] ?? 'soap_fault'));
        $faultMessage = (string) ($fault['faultstring'] ?? $fault['Reason']['Text'] ?? 'Sabre SOAP fault');
        $context = ['fault' => $fault];

        if (str_contains($faultCode, 'auth') || str_contains($faultCode, 'security') || str_contains(strtolower($faultMessage), 'token')) {
            throw new ProviderAuthException($faultMessage, 'sabre', 401);
        }

        throw new ProviderValidationException($faultMessage, 'sabre', $context);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return mixed
     */
    private function findNodeByKey(array $payload, string $needle): mixed
    {
        foreach ($payload as $key => $value) {
            if (str_contains(strtolower((string) $key), strtolower($needle))) {
                return $value;
            }
            if (is_array($value)) {
                $nested = $this->findNodeByKey($value, $needle);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }
}

