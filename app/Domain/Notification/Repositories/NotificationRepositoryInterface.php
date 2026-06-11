<?php

declare(strict_types=1);

namespace App\Domain\Notification\Repositories;

use App\Domain\Notification\Entities\Notification;
use App\Domain\Notification\Enums\NotificationStatus;

interface NotificationRepositoryInterface
{
    public function findById(int $id): ?Notification;

    /** @return Notification[] */
    public function findByRecipientId(int $recipientId): array;

    public function save(Notification $notification): Notification;

    public function updateStatus(int $id, NotificationStatus $status, ?string $providerMessageId = null): void;

    public function incrementRetryCount(int $id): void;
}
