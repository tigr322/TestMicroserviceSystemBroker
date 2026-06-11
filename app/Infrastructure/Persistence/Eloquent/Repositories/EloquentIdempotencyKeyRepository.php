<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Idempotency\Entities\IdempotencyKey;
use App\Domain\Idempotency\Repositories\IdempotencyKeyRepositoryInterface;
use App\Infrastructure\Cache\RedisIdempotencyCache;
use App\Infrastructure\Persistence\Eloquent\Models\IdempotencyKeyModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Two-layer idempotency store:
 *   1. Redis — fast O(1) hot-path lookup
 *   2. PostgreSQL — durable fallback after Redis eviction / restart
 */
final class EloquentIdempotencyKeyRepository implements IdempotencyKeyRepositoryInterface
{
    public function __construct(
        private readonly RedisIdempotencyCache $cache,
    ) {}

    public function findByKey(string $key): ?IdempotencyKey
    {
        // Hot path — Redis
        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return $cached;
        }

        // Cold path — PostgreSQL
        $model = IdempotencyKeyModel::where('key', $key)->first();
        if ($model === null) {
            return null;
        }

        $entity = $this->toDomain($model);

        // Re-warm Redis cache if the record hasn't expired yet
        if (! $entity->isExpired()) {
            $remainingTtl = $entity->expiresAt
                ? max(1, (int) ($entity->expiresAt->getTimestamp() - (new DateTimeImmutable)->getTimestamp()))
                : 86400;

            $this->cache->put($key, $entity, max(1, $remainingTtl));
        }

        return $entity;
    }

    public function save(string $key, string $requestHash, array $responsePayload, int $ttlSeconds): IdempotencyKey
    {
        $expiresAt = (new DateTimeImmutable)->modify("+{$ttlSeconds} seconds");

        $model = DB::transaction(fn () => IdempotencyKeyModel::firstOrCreate(
            ['key' => $key],
            [
                'request_hash'     => $requestHash,
                'response_payload' => $responsePayload,
                'expires_at'       => $expiresAt,
                'created_at'       => now(),
            ]
        ));

        $entity = $this->toDomain($model);

        // Always write to Redis regardless of whether the DB row was new
        $this->cache->put($key, $entity, $ttlSeconds);

        return $entity;
    }

    public function exists(string $key): bool
    {
        if ($this->cache->has($key)) {
            return true;
        }

        return IdempotencyKeyModel::where('key', $key)->exists();
    }

    private function toDomain(IdempotencyKeyModel $model): IdempotencyKey
    {
        return new IdempotencyKey(
            id: $model->id,
            key: $model->key,
            requestHash: $model->request_hash,
            responsePayload: $model->response_payload,
            expiresAt: $model->expires_at
                ? DateTimeImmutable::createFromMutable($model->expires_at->toDateTime())
                : null,
            createdAt: DateTimeImmutable::createFromMutable($model->created_at->toDateTime()),
        );
    }
}
