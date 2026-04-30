<?php

namespace App\Automation\Providers;

use App\Contracts\Automation\AutomationProviderInterface;
use Illuminate\Support\Facades\Http;

final class OpenSourceWebhookAutomationProvider implements AutomationProviderInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendReminder(string $eventName, array $payload): void
    {
        $this->post('/automation/reminder', $eventName, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function triggerEvent(string $eventName, array $payload): void
    {
        $this->post('/automation/event', $eventName, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, string $eventName, array $payload): void
    {
        $baseUrl = rtrim((string) config('automation.open_source_webhook.base_url', ''), '/');
        if ($baseUrl === '') {
            return;
        }

        $token = (string) config('automation.open_source_webhook.token', '');
        $timeout = max(1, (int) config('automation.open_source_webhook.timeout_seconds', 10));

        Http::timeout($timeout)
            ->withToken($token)
            ->acceptJson()
            ->post($baseUrl.$path, [
                'event' => $eventName,
                'payload' => $payload,
                'sent_at' => now()->toIso8601String(),
            ])
            ->throw();
    }
}
