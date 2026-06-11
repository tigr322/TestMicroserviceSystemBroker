<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers\Notification;

final readonly class ProviderResult
{
    public function __construct(
        public string $messageId,
        public string $providerName,
    ) {}
}
