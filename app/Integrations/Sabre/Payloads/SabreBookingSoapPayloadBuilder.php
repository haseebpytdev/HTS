<?php

namespace App\Integrations\Sabre\Payloads;

use App\Data\Integrations\BookingCreateRequestData;
use App\Data\Integrations\TravelerData;

final class SabreBookingSoapPayloadBuilder
{
    public function buildCreateBookingEnvelope(BookingCreateRequestData $request): string
    {
        $passengers = implode("\n", array_map(function (TravelerData $traveler, int $index): string {
            $given = htmlspecialchars($traveler->givenName, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $family = htmlspecialchars($traveler->familyName, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $type = htmlspecialchars(strtoupper($traveler->travelerType), ENT_XML1 | ENT_QUOTES, 'UTF-8');

            return <<<XML
        <stl:Passenger PassengerType="{$type}" NameNumber="{$index}.1">
          <stl:GivenName>{$given}</stl:GivenName>
          <stl:Surname>{$family}</stl:Surname>
        </stl:Passenger>
XML;
        }, $request->travelers, array_keys($request->travelers)));

        $offerReference = htmlspecialchars($request->offerReference, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:stl="http://services.sabre.com/STL_Payload/v02_01">
  <soapenv:Header/>
  <soapenv:Body>
    <stl:CreatePassengerNameRecordRQ version="2.5.0">
      <stl:OfferReference>{$offerReference}</stl:OfferReference>
{$passengers}
    </stl:CreatePassengerNameRecordRQ>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    public function buildRetrieveEnvelope(string $bookingReference): string
    {
        $ref = htmlspecialchars($bookingReference, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:stl="http://services.sabre.com/STL_Payload/v02_01">
  <soapenv:Header/>
  <soapenv:Body>
    <stl:GetReservationRQ version="1.19.0">
      <stl:Locator>{$ref}</stl:Locator>
    </stl:GetReservationRQ>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    public function buildCancelEnvelope(string $bookingReference): string
    {
        $ref = htmlspecialchars($bookingReference, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:stl="http://services.sabre.com/STL_Payload/v02_01">
  <soapenv:Header/>
  <soapenv:Body>
    <stl:CancelReservationRQ version="1.0.0">
      <stl:Locator>{$ref}</stl:Locator>
    </stl:CancelReservationRQ>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }
}

