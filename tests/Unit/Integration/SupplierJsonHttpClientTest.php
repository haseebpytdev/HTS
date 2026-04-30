<?php

namespace Tests\Unit\Integration;

use App\Integrations\Shared\Exceptions\ProviderMappingException;
use App\Integrations\Shared\Exceptions\ProviderValidationException;
use App\Integrations\Shared\SupplierJsonHttpClient;
use App\Integrations\Stub\StubAuthTokenAdapter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupplierJsonHttpClientTest extends TestCase
{
    public function test_get_decodes_json_and_returns_supplier_response(): void
    {
        Http::fake([
            'https://api.supplier.test/v1/ping*' => Http::response(['alive' => true], 200),
        ]);

        $client = new SupplierJsonHttpClient(new StubAuthTokenAdapter, 'test-provider', 'https://api.supplier.test');
        $res = $client->get('v1/ping', ['x' => '1']);

        $this->assertSame(200, $res->statusCode);
        $this->assertTrue($res->decodedJson['alive'] ?? false);
        $this->assertTrue($res->successful());
    }

    public function test_absolute_uri_skips_base_url(): void
    {
        Http::fake([
            'https://other.example/full' => Http::response(['ok' => 1], 201),
        ]);

        $client = new SupplierJsonHttpClient(new StubAuthTokenAdapter, 'test-provider', 'https://api.supplier.test');
        $res = $client->get('https://other.example/full');

        $this->assertSame(201, $res->statusCode);
        $this->assertSame(1, $res->decodedJson['ok'] ?? null);
    }

    public function test_empty_body_yields_empty_decoded_array(): void
    {
        Http::fake([
            'https://api.supplier.test/empty' => Http::response('', 204),
        ]);

        $client = new SupplierJsonHttpClient(new StubAuthTokenAdapter, 'test-provider', 'https://api.supplier.test');
        $res = $client->get('https://api.supplier.test/empty');

        $this->assertSame(204, $res->statusCode);
        $this->assertSame([], $res->decodedJson);
    }

    public function test_invalid_json_throws_mapping_exception(): void
    {
        Http::fake([
            'https://api.supplier.test/bad' => Http::response('not-json', 200),
        ]);

        $client = new SupplierJsonHttpClient(new StubAuthTokenAdapter, 'test-provider', 'https://api.supplier.test');

        $this->expectException(ProviderMappingException::class);
        $client->get('https://api.supplier.test/bad');
    }

    public function test_client_error_throws_validation_exception(): void
    {
        Http::fake([
            'https://api.supplier.test/nope' => Http::response(['err' => 'bad'], 422),
        ]);

        $client = new SupplierJsonHttpClient(new StubAuthTokenAdapter, 'test-provider', 'https://api.supplier.test');

        $this->expectException(ProviderValidationException::class);
        $client->get('https://api.supplier.test/nope');
    }

    public function test_missing_base_url_throws(): void
    {
        $client = new SupplierJsonHttpClient(new StubAuthTokenAdapter, 'test-provider', null);

        $this->expectException(ProviderMappingException::class);
        $client->get('relative/path');
    }
}
