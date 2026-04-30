<?php

namespace App\Communication\Channels;

use App\Contracts\Communication\WhatsappChannelInterface;
use App\Data\Communication\CommunicationMessageData;
use Illuminate\Support\Facades\Http;

class OpenSourceWebhookWhatsappChannel implements WhatsappChannelInterface
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

        $baseUrl = rtrim((string) config('communication.whatsapp.webhook_base_url', ''), '/');
        if ($baseUrl === '') {
            return;
        }

        $token = (string) config('communication.whatsapp.token', '');
        $timeout = max(1, (int) config('communication.whatsapp.timeout_seconds', 10));

        Http::timeout($timeout)
            ->withToken($token)
            ->acceptJson()
            ->post($baseUrl.'/communication/whatsapp', [
                'to' => $message->phone,
                'subject' => $message->subject,
                'body' => $message->body,
                'meta' => $message->meta,
                'sent_at' => now()->toIso8601String(),
            ])
            ->throw();
    }
}
