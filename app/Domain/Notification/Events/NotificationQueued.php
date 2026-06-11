<?php

declare(strict_types=1);

namespace App\Domain\Notification\Events;

final readonly class NotificationQueued
{
    public function __construct(
        public int $notificationId,
        public int $recipientId,
        public string $channel,
        public string $priority,
    ) {}
}
