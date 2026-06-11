<?php

declare(strict_types=1);

namespace App\Domain\Notification\Enums;

enum Priority: string
{
    case High   = 'high';
    case Normal = 'normal';
    case Low    = 'low';

    /**
     * Returns the RabbitMQ / Horizon queue name for this priority.
     * Workers are started with --queue=notifications.high,notifications.normal,notifications.low
     * which guarantees high-priority messages are always consumed first.
     */
    public function queue(): string
    {
        return 'notifications.' . $this->value;
    }

    /** Numeric weight — useful for sorting / assertions in tests */
    public function weight(): int
    {
        return match ($this) {
            self::High   => 3,
            self::Normal => 2,
            self::Low    => 1,
        };
    }
}
