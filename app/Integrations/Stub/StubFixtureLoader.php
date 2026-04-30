<?php

namespace App\Integrations\Stub;

use RuntimeException;

final class StubFixtureLoader
{
    /**
     * @return array<string, mixed>
     */
    public static function load(string $provider, string $fixture): array
    {
        $path = base_path("tests/Fixtures/integrations/{$provider}/{$fixture}.json");
        if (! is_file($path)) {
            throw new RuntimeException("Missing stub fixture: {$provider}/{$fixture}.json");
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
