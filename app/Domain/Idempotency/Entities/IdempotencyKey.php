<?php

declare(strict_types=1);

namespace App\Domain\Idempotency\Entities;

use DateTimeImmutable;

final readonly class IdempotencyKey
{
    public function __construct(
        public int $id,
        public string $key,
        public string $requestHash,
        public array $responsePayload,
        public ?DateTimeImmutable $expiresAt,
        public DateTimeImmutable $createdAt,
    ) {}

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTimeImmutable;
    }
}
