<?php

namespace App\Integrations\AmadeusSelfService\Support;

final class AmadeusSelfServiceProvider
{
    public const CODE = 'amadeus_self_service';
    public const LEGACY_CODE = 'amadeus';

    /**
     * @return list<string>
     */
    public static function aliases(): array
    {
        return [self::CODE, self::LEGACY_CODE];
    }

    public static function normalize(string $provider): string
    {
        $value = strtolower(trim($provider));

        return in_array($value, self::aliases(), true) ? self::CODE : $value;
    }

    public static function matches(string $provider): bool
    {
        return in_array(strtolower(trim($provider)), self::aliases(), true);
    }
}

