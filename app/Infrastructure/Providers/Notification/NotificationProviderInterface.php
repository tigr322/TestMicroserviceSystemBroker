<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers\Notification;

use App\Domain\Notification\Entities\Notification;

interface NotificationProviderInterface
{
    /**
     * Attempt to deliver a notification via the underlying channel.
     *
     * @throws TemporaryProviderException  Transient error — the job should be retried.
     * @throws PermanentProviderException  Fatal error — mark the notification as failed immediately.
     */
    public function send(Notification $notification): ProviderResult;
}
