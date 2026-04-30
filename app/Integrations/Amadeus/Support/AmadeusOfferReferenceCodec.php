<?php

namespace App\Integrations\Amadeus\Support;

final class AmadeusOfferReferenceCodec
{
    private const PREFIX = 'amadeus:';

    /**
     * @param  array<string, mixed>  $offer
     */
    public function encode(array $offer): string
    {
        $json = json_encode($offer);
        if (! is_string($json) || $json === '') {
            return self::PREFIX;
        }

        return self::PREFIX.base64_encode($json);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decode(string $reference): ?array
    {
        if (! str_starts_with($reference, self::PREFIX)) {
            return null;
        }

        $encoded = substr($reference, strlen(self::PREFIX));
        if (! is_string($encoded) || $encoded === '') {
            return null;
        }

        $json = base64_decode($encoded, true);
        if (! is_string($json) || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}

