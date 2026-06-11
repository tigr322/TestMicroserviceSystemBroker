<?php

declare(strict_types=1);

namespace App\Domain\Notification\Enums;

enum NotificationStatus: string
{
    case Queued    = 'queued';
    case Sent      = 'sent';
    case Delivered = 'delivered';
    case Failed    = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Delivered, self::Failed => true,
            default                       => false,
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Queued    => in_array($next, [self::Sent, self::Failed], true),
            self::Sent      => in_array($next, [self::Delivered, self::Failed], true),
            self::Delivered => false,
            self::Failed    => false,
        };
    }
}
