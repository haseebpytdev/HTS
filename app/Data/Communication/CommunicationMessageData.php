<?php

namespace App\Data\Communication;

final class CommunicationMessageData
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $meta = [],
    ) {
    }
}
