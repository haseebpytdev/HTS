<?php

namespace Tests\Unit\Integration;

use App\Contracts\Integrations\AuthTokenProviderInterface;
use App\Integrations\Sabre\SabreSoapClient;
use App\Integrations\Shared\Exceptions\ProviderAuthException;
use App\Integrations\Shared\Exceptions\ProviderValidationException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreSoapClientTest extends TestCase
{
    public function test_posts_soap_request_and_parses_xml_response(): void
    {
        Http::fake([
            'https://soap.sabre.test/*' => Http::response(
                <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Envelope>
  <Body>
    <OTA_AirLowFareSearchRS>
      <Success>true</Success>
    </OTA_AirLowFareSearchRS>
  </Body>
</Envelope>
XML,
                200,
                ['Content-Type' => 'text/xml']
            ),
        ]);

        $auth = new class implements AuthTokenProviderInterface
        {
            public function providerCode(): string
            {
                return 'sabre';
            }

            public function getAccessToken(): string
            {
                return 'token';
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
        };

        $client = new SabreSoapClient($auth, 'https://soap.sabre.test');
        $response = $client->request('/ota', 'OTA_AirLowFareSearchRQ', '<Envelope><Body/></Envelope>');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('true', (string) data_get($response->decodedJson, 'Body.OTA_AirLowFareSearchRS.Success'));
        Http::assertSentCount(1);
    }

    public function test_throws_validation_exception_for_soap_fault(): void
    {
        Http::fake([
            'https://soap.sabre.test/*' => Http::response(
                <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Envelope>
  <Body>
    <Fault>
      <faultcode>SOAP-ENV:Client</faultcode>
      <faultstring>Invalid request</faultstring>
    </Fault>
  </Body>
</Envelope>
XML,
                200,
                ['Content-Type' => 'text/xml']
            ),
        ]);

        $this->expectException(ProviderValidationException::class);
        (new SabreSoapClient($this->authMock(), 'https://soap.sabre.test'))
            ->request('/ota', 'OTA_AirLowFareSearchRQ', '<Envelope><Body/></Envelope>');
    }

    public function test_throws_auth_exception_for_security_fault(): void
    {
        Http::fake([
            'https://soap.sabre.test/*' => Http::response(
                <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Envelope>
  <Body>
    <Fault>
      <faultcode>Security.Auth</faultcode>
      <faultstring>Token expired</faultstring>
    </Fault>
  </Body>
</Envelope>
XML,
                200,
                ['Content-Type' => 'text/xml']
            ),
        ]);

        $this->expectException(ProviderAuthException::class);
        (new SabreSoapClient($this->authMock(), 'https://soap.sabre.test'))
            ->request('/ota', 'OTA_AirLowFareSearchRQ', '<Envelope><Body/></Envelope>');
    }

    private function authMock(): AuthTokenProviderInterface
    {
        return new class implements AuthTokenProviderInterface
        {
            public function providerCode(): string
            {
                return 'sabre';
            }

            public function getAccessToken(): string
            {
                return 'token';
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
        };
    }
}

