<?php

declare(strict_types=1);

namespace App\Application\DTOs;

final readonly class NotificationResultDTO
{
    /**
     * @param int[] $notificationIds
     */
    public function __construct(
        public array $notificationIds,
        public bool  $deduplicated,
        public string $message,
    ) {}

    public function toArray(): array
    {
        return [
            'notification_ids' => $this->notificationIds,
            'deduplicated'     => $this->deduplicated,
            'message'          => $this->message,
        ];
    }
}
