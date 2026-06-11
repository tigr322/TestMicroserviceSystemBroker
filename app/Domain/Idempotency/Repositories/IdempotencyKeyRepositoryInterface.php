<?php

declare(strict_types=1);

namespace App\Domain\Idempotency\Repositories;

use App\Domain\Idempotency\Entities\IdempotencyKey;

interface IdempotencyKeyRepositoryInterface
{
    public function findByKey(string $key): ?IdempotencyKey;

    public function save(string $key, string $requestHash, array $responsePayload, int $ttlSeconds): IdempotencyKey;

    public function exists(string $key): bool;
}
