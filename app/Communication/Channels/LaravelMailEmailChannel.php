<?php

namespace App\Communication\Channels;

use App\Contracts\Communication\EmailChannelInterface;
use App\Data\Communication\CommunicationMessageData;
use Illuminate\Support\Facades\Mail;

class LaravelMailEmailChannel implements EmailChannelInterface
{
    public function providerCode(): string
    {
        return 'laravel_mail';
    }

    public function send(CommunicationMessageData $message): void
    {
        if (blank($message->email)) {
            return;
        }

        Mail::raw($message->body, function ($mail) use ($message): void {
            $mail->to($message->email)
                ->subject($message->subject)
                ->from(
                    (string) config('communication.email.default_from_address'),
                    (string) config('communication.email.default_from_name')
                );
        });
    }
}
