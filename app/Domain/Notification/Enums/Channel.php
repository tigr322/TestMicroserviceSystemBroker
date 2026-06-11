<?php

declare(strict_types=1);

namespace App\Domain\Notification\Enums;

enum Channel: string
{
    case Email = 'email';
    case Sms   = 'sms';

    /** Human-readable label */
    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Sms   => 'SMS',
        };
    }
}
