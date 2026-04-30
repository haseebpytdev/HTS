<?php

namespace Tests\Unit\Integration;

use App\Integrations\Sabre\Support\SabreSoapPayloadSanitizer;
use Tests\TestCase;

class SabreSoapPayloadSanitizerTest extends TestCase
{
    public function test_masks_sensitive_array_keys(): void
    {
        $sanitizer = new SabreSoapPayloadSanitizer;
        $masked = $sanitizer->sanitizeArray([
            'token' => 'abc',
            'Authorization' => 'Bearer xyz',
            'nested' => ['client_secret' => 'very-secret', 'name' => 'ok'],
        ]);

        $this->assertSame('***', $masked['token']);
        $this->assertSame('***', $masked['Authorization']);
        $this->assertSame('***', $masked['nested']['client_secret']);
        $this->assertSame('ok', $masked['nested']['name']);
    }

    public function test_masks_sensitive_xml_tags(): void
    {
        $sanitizer = new SabreSoapPayloadSanitizer;
        $xml = '<Envelope><token>abc</token><password>pwd</password></Envelope>';
        $masked = $sanitizer->sanitizeXml($xml);

        $this->assertStringNotContainsString('abc', $masked);
        $this->assertStringNotContainsString('pwd', $masked);
        $this->assertStringContainsString('***', $masked);
    }
}

