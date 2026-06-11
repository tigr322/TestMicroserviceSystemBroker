<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers\Notification;

use App\Domain\Notification\Entities\Notification;
use Illuminate\Support\Str;

/**
 * Mock SMS provider that randomly simulates the three outcomes:
 *   success (60%)  | temporary failure (25%)  | permanent failure (15%)
 *
 * Outcome percentages are overridable via the constructor — handy for testing.
 */
final class SmsProviderMock implements NotificationProviderInterface
{
    public function __construct(
        /** 0-100, probability of a successful send */
        private readonly int $successRate = 60,
        /** 0-100, probability of a temporary (retriable) failure */
        private readonly int $temporaryFailureRate = 25,
        // Permanent failure fills the remainder
    ) {}

    public function send(Notification $notification): ProviderResult
    {
        $roll = random_int(1, 100);

        if ($roll <= $this->successRate) {
            return new ProviderResult(
                messageId: 'sms_' . Str::uuid()->toString(),
                providerName: 'SmsProviderMock',
            );
        }

        if ($roll <= $this->successRate + $this->temporaryFailureRate) {
            throw new TemporaryProviderException(
                "SMS gateway temporarily unavailable (mock). Notification #{$notification->id}"
            );
        }

        throw new PermanentProviderException(
            "SMS delivery permanently failed (mock) — invalid number. Notification #{$notification->id}"
        );
    }
}
