<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers\Notification;

use App\Domain\Notification\Enums\Channel;
use InvalidArgumentException;

final class NotificationProviderFactory
{
    public function make(Channel $channel): NotificationProviderInterface
    {
        return match ($channel) {
            Channel::Sms   => new SmsProviderMock,
            Channel::Email => new EmailProviderMock,
            default        => throw new InvalidArgumentException(
                "No provider registered for channel: {$channel->value}"
            ),
        };
    }
}
