<?php

namespace App\Contracts\Automation;

interface AutomationProviderInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendReminder(string $eventName, array $payload): void;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function triggerEvent(string $eventName, array $payload): void;
}
