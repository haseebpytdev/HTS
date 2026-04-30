<?php

namespace App\Contracts\Communication;

use App\Data\Communication\CommunicationMessageData;

interface WhatsappChannelInterface
{
    public function providerCode(): string;

    public function send(CommunicationMessageData $message): void;
}
