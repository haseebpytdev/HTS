<?php

namespace App\Jobs\Communication;

use App\Data\Communication\CommunicationMessageData;
use App\Services\Communication\CommunicationHub;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendInAppNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly int $userId,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $meta = [],
    ) {
    }

    public function handle(CommunicationHub $hub): void
    {
        $hub->sendInApp($this->userId, new CommunicationMessageData(
            email: $this->email,
            phone: $this->phone,
            subject: $this->subject,
            body: $this->body,
            meta: $this->meta
        ));
    }
}
