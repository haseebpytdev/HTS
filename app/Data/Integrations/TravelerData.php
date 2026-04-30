<?php

namespace App\Data\Integrations;

use JsonSerializable;

/**
 * Passenger / traveler in normalized form.
 */
final readonly class TravelerData implements JsonSerializable
{
    public function __construct(
        public string $travelerType,
        public string $givenName,
        public string $familyName,
        public ?string $dateOfBirth = null,
        public ?string $nationality = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'traveler_type' => $this->travelerType,
            'given_name' => $this->givenName,
            'family_name' => $this->familyName,
            'date_of_birth' => $this->dateOfBirth,
            'nationality' => $this->nationality,
        ];
    }
}
