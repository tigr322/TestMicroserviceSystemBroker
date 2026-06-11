<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers\Notification;

use App\Domain\Notification\Entities\Notification;
use Illuminate\Support\Str;

/**
 * Mock Email provider — same probability model as SmsProviderMock.
 * success (65%)  | temporary failure (20%)  | permanent failure (15%)
 */
final class EmailProviderMock implements NotificationProviderInterface
{
    public function __construct(
        private readonly int $successRate = 65,
        private readonly int $temporaryFailureRate = 20,
    ) {}

    public function send(Notification $notification): ProviderResult
    {
        $roll = random_int(1, 100);

        if ($roll <= $this->successRate) {
            return new ProviderResult(
                messageId: 'email_' . Str::uuid()->toString(),
                providerName: 'EmailProviderMock',
            );
        }

        if ($roll <= $this->successRate + $this->temporaryFailureRate) {
            throw new TemporaryProviderException(
                "SMTP server temporarily unavailable (mock). Notification #{$notification->id}"
            );
        }

        throw new PermanentProviderException(
            "Email delivery permanently failed (mock) — hard bounce. Notification #{$notification->id}"
        );
    }
}
