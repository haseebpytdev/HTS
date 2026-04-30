<?php

namespace App\Services\Communication;

use App\Contracts\Communication\EmailChannelInterface;
use App\Contracts\Communication\InAppNotificationChannelInterface;
use App\Contracts\Communication\SmsChannelInterface;
use App\Contracts\Communication\WhatsappChannelInterface;
use App\Data\Communication\CommunicationMessageData;

class CommunicationHub
{
    public function __construct(
        private readonly EmailChannelInterface $emailChannel,
        private readonly WhatsappChannelInterface $whatsappChannel,
        private readonly SmsChannelInterface $smsChannel,
        private readonly InAppNotificationChannelInterface $inAppNotificationChannel,
    ) {
    }

    public function sendEmail(CommunicationMessageData $message): void
    {
        $this->emailChannel->send($message);
    }

    public function sendWhatsapp(CommunicationMessageData $message): void
    {
        $this->whatsappChannel->send($message);
    }

    public function sendSms(CommunicationMessageData $message): void
    {
        $this->smsChannel->send($message);
    }

    public function sendInApp(int $userId, CommunicationMessageData $message): void
    {
        $this->inAppNotificationChannel->sendToUserId($userId, $message);
    }
}
