<?php

namespace App\Integrations\Sabre\Payloads;

final class SabreFlightPricingSoapPayloadBuilder
{
    /**
     * @param  array<string, mixed>  $opaqueContext
     */
    public function buildEnvelope(string $offerReference, array $opaqueContext = []): string
    {
        $currency = strtoupper((string) ($opaqueContext['currency'] ?? 'USD'));
        $pricingToken = htmlspecialchars($offerReference, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ota="http://www.opentravel.org/OTA/2003/05">
  <soapenv:Header/>
  <soapenv:Body>
    <ota:OTA_AirPriceRQ Version="3.0.0">
      <ota:PriceRequestInformation CurrencyCode="{$currency}">
        <ota:OptionalQualifiers>
          <ota:PricingToken>{$pricingToken}</ota:PricingToken>
        </ota:OptionalQualifiers>
      </ota:PriceRequestInformation>
    </ota:OTA_AirPriceRQ>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }
}

