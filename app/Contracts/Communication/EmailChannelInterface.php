<?php

namespace App\Contracts\Communication;

use App\Data\Communication\CommunicationMessageData;

interface EmailChannelInterface
{
    public function providerCode(): string;

    public function send(CommunicationMessageData $message): void;
}
