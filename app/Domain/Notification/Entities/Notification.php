<?php

declare(strict_types=1);

namespace App\Domain\Notification\Entities;

use App\Domain\Notification\Enums\Channel;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Enums\Priority;
use App\Domain\Notification\Exceptions\InvalidStatusTransitionException;
use DateTimeImmutable;

/**
 * Pure domain entity — no Eloquent, no framework dependencies.
 */
final class Notification
{
    public function __construct(
        public readonly int $id,
        public readonly int $recipientId,
        public readonly Channel $channel,
        public readonly Priority $priority,
        public readonly string $message,
        public NotificationStatus $status,
        public int $retryCount,
        public ?string $providerMessageId,
        public readonly ?string $idempotencyKey,
        public readonly DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    /** Mark the notification as being actively sent (queued → sent). */
    public function markAsSent(): void
    {
        $this->transition(NotificationStatus::Sent);
    }

    /** Mark the notification as confirmed delivered (sent → delivered). */
    public function markAsDelivered(?string $providerMessageId = null): void
    {
        $this->transition(NotificationStatus::Delivered);
        $this->providerMessageId = $providerMessageId;
    }

    /** Mark the notification as permanently failed. */
    public function markAsFailed(): void
    {
        // Allow transition from any non-terminal state
        if (! $this->status->isTerminal()) {
            $this->status = NotificationStatus::Failed;
            $this->updatedAt = new DateTimeImmutable;
        }
    }

    public function incrementRetryCount(): void
    {
        ++$this->retryCount;
    }

    private function transition(NotificationStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw new InvalidStatusTransitionException(
                "Cannot transition from {$this->status->value} to {$next->value} (notification #{$this->id})"
            );
        }

        $this->status    = $next;
        $this->updatedAt = new DateTimeImmutable;
    }
}
