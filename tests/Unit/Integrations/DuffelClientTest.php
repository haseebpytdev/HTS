<?php

namespace Tests\Unit\Integrations;

use App\Contracts\Integrations\AuthTokenProviderInterface;
use App\Contracts\Integrations\SupplierJsonHttpClientInterface;
use App\Data\Integrations\SupplierHttpResponse;
use App\Integrations\Duffel\DuffelClient;
use App\Integrations\Duffel\DuffelConfig;
use App\Integrations\Shared\Exceptions\ProviderAuthException;
use App\Integrations\Shared\Exceptions\SupplierIntegrationException;
use Tests\TestCase;

class DuffelClientTest extends TestCase
{
    public function test_create_offer_request_uses_duffel_paths_and_default_headers(): void
    {
        $json = $this->createMock(SupplierJsonHttpClientInterface::class);
        $json->method('baseUrl')->willReturn('https://api.duffel.com');
        $json->expects($this->once())
            ->method('post')
            ->with(
                '/air/offer_requests?return_offers=true',
                ['data' => ['type' => 'offer_requests']],
                $this->callback(function (array $headers): bool {
                    return ($headers['Accept'] ?? null) === 'application/json'
                        && ($headers['Content-Type'] ?? null) === 'application/json'
                        && ($headers['Duffel-Version'] ?? null) === 'v2'
                        && ($headers['X-Correlation-ID'] ?? null) === 'corr-123';
                })
            )
            ->willReturn(new SupplierHttpResponse(201, '{"data":[]}', ['data' => []]));

        $client = new DuffelClient(
            auth: new TestAuthTokenProvider(),
            jsonHttp: $json,
            config: new DuffelConfig(
                baseUrl: 'https://api.duffel.com',
                timeoutSeconds: 20,
                offerRequestsPath: '/air/offer_requests',
                offersPath: '/air/offers',
                ordersPath: '/air/orders',
                defaultHeaders: [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Duffel-Version' => 'v2',
                ],
            ),
        );

        $response = $client->createOfferRequest(
            payload: ['data' => ['type' => 'offer_requests']],
            headers: ['X-Correlation-ID' => 'corr-123'],
        );

        $this->assertTrue($response->successful());
        $this->assertSame(201, $response->statusCode);
    }

    public function test_get_order_builds_order_resource_path(): void
    {
        $json = $this->createMock(SupplierJsonHttpClientInterface::class);
        $json->method('baseUrl')->willReturn('https://api.duffel.com');
        $json->expects($this->once())
            ->method('get')
            ->with(
                '/air/orders/ord_123',
                ['return_available_actions' => 'true'],
                $this->isType('array')
            )
            ->willReturn(new SupplierHttpResponse(200, '{"data":{"id":"ord_123"}}', ['data' => ['id' => 'ord_123']]));

        $client = new DuffelClient($this->auth(), $json);
        $response = $client->getOrder('ord_123', ['return_available_actions' => 'true']);

        $this->assertSame('ord_123', (string) ($response->decodedJson['data']['id'] ?? ''));
    }

    public function test_maps_provider_auth_exception_to_supplier_integration_exception(): void
    {
        $json = $this->createMock(SupplierJsonHttpClientInterface::class);
        $json->method('baseUrl')->willReturn('https://api.duffel.com');
        $json->expects($this->once())
            ->method('post')
            ->willThrowException(new ProviderAuthException('Invalid Duffel token', 'duffel', 401));

        $client = new DuffelClient($this->auth(), $json);

        try {
            $client->createOrder(['data' => ['type' => 'orders']]);
            $this->fail('Expected SupplierIntegrationException was not thrown.');
        } catch (SupplierIntegrationException $e) {
            $this->assertSame('supplier_auth_failed', $e->normalizedCode);
            $this->assertSame('DUFFEL_AUTH_FAILED', $e->supplierCode);
            $this->assertSame(401, $e->apiError?->httpStatus);
            $this->assertSame('POST', $e->supplierContext['method'] ?? null);
            $this->assertSame('/air/orders', $e->supplierContext['uri'] ?? null);
        }
    }

    public function test_duffel_config_reads_test_environment_defaults(): void
    {
        config([
            'integrations.credential_environment' => 'test',
            'duffel.environments.test.base_url' => 'https://api.duffel.test',
            'duffel.version' => 'v2',
            'duffel.timeout_seconds' => 45,
            'duffel.offer_requests_path' => '/air/offer_requests',
            'duffel.offers_path' => '/air/offers',
            'duffel.orders_path' => '/air/orders',
        ]);

        $config = DuffelConfig::fromConfig();

        $this->assertSame('https://api.duffel.test', $config->baseUrl);
        $this->assertSame(45, $config->timeoutSeconds);
        $this->assertSame('v2', $config->defaultHeaders['Duffel-Version'] ?? null);
    }

    private function auth(): AuthTokenProviderInterface
    {
        return new TestAuthTokenProvider();
    }
}

final class TestAuthTokenProvider implements AuthTokenProviderInterface
{
    public function providerCode(): string
    {
        return 'duffel';
    }

    public function getAccessToken(): string
    {
        return 'duffel_test_token';
    }

    public function refreshTokenIfNeeded(): void
    {
    }

    public function invalidateCachedToken(): void
    {
    }

    public function executeWithAuthRetry(callable $operation): mixed
    {
        return $operation();
    }
}

