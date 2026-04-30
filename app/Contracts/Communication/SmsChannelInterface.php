<?php

namespace App\Contracts\Communication;

use App\Data\Communication\CommunicationMessageData;

interface SmsChannelInterface
{
    public function providerCode(): string;

    public function send(CommunicationMessageData $message): void;
}
