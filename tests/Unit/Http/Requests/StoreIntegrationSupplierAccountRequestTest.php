<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\Admin\StoreIntegrationSupplierAccountRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreIntegrationSupplierAccountRequestTest extends TestCase
{
    public function test_rejects_token_endpoint_path_in_base_url(): void
    {
        $request = new StoreIntegrationSupplierAccountRequest;

        $invalid = Validator::make([
            'name' => 'Sabre Sandbox',
            'ownership_type' => 'platform_owner',
            'provider' => 'sabre',
            'test' => [
                'base_url' => 'https://api.cert.platform.sabre.com/v2/auth/token',
            ],
        ], $request->rules());

        $this->assertTrue($invalid->fails());
        $this->assertArrayHasKey('test.base_url', $invalid->errors()->toArray());

        $valid = Validator::make([
            'name' => 'Sabre Sandbox',
            'ownership_type' => 'platform_owner',
            'provider' => 'sabre',
            'test' => [
                'base_url' => 'https://api.cert.platform.sabre.com',
            ],
        ], $request->rules());

        $this->assertFalse($valid->fails());
    }
}
