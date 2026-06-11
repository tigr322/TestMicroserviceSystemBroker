<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use App\Domain\Notification\Enums\Channel;
use App\Domain\Notification\Enums\Priority;

final readonly class SendNotificationDTO
{
    /**
     * @param int[] $recipientIds
     */
    public function __construct(
        public Channel $channel,
        public Priority $priority,
        public string $message,
        public array $recipientIds,
        public ?string $idempotencyKey,
    ) {}

    /** Deterministic hash of the business payload — used for duplicate detection. */
    public function requestHash(): string
    {
        return hash('sha256', json_encode([
            'channel'       => $this->channel->value,
            'priority'      => $this->priority->value,
            'message'       => $this->message,
            'recipient_ids' => $this->recipientIds,
        ], JSON_THROW_ON_ERROR));
    }
}
