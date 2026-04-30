<?php

namespace App\Contracts\Communication;

use App\Data\Communication\CommunicationMessageData;

interface InAppNotificationChannelInterface
{
    public function providerCode(): string;

    public function sendToUserId(int $userId, CommunicationMessageData $message): void;
}
