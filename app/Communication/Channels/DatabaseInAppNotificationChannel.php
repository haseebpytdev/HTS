<?php

namespace App\Communication\Channels;

use App\Contracts\Communication\InAppNotificationChannelInterface;
use App\Data\Communication\CommunicationMessageData;
use App\Models\User;
use App\Notifications\GenericInAppNotification;

class DatabaseInAppNotificationChannel implements InAppNotificationChannelInterface
{
    public function providerCode(): string
    {
        return 'database';
    }

    public function sendToUserId(int $userId, CommunicationMessageData $message): void
    {
        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }

        $user->notify(new GenericInAppNotification($message->subject, $message->body, $message->meta));
    }
}
