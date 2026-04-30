<?php

namespace App\Communication\Channels;

use App\Contracts\Communication\SmsChannelInterface;
use App\Data\Communication\CommunicationMessageData;
use Illuminate\Support\Facades\Http;

class OpenSourceWebhookSmsChannel implements SmsChannelInterface
{
    public function providerCode(): string
    {
        return 'open_source_webhook';
    }

    public function send(CommunicationMessageData $message): void
    {
        if (blank($message->phone)) {
            return;
        }

        $baseUrl = rtrim((string) config('communication.sms.webhook_base_url', ''), '/');
        if ($baseUrl === '') {
            return;
        }

        $token = (string) config('communication.sms.token', '');
        $timeout = max(1, (int) config('communication.sms.timeout_seconds', 10));

        Http::timeout($timeout)
            ->withToken($token)
            ->acceptJson()
            ->post($baseUrl.'/communication/sms', [
                'to' => $message->phone,
                'subject' => $message->subject,
                'body' => $message->body,
                'meta' => $message->meta,
                'sent_at' => now()->toIso8601String(),
            ])
            ->throw();
    }
}
