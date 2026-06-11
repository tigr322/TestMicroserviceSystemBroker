<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Domain\Idempotency\Entities\IdempotencyKey;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Redis-backed idempotency cache.
 *
 * Key format:  idempotency:{key}
 * Value:       JSON-encoded IdempotencyKey attributes
 * TTL:         inherited from the original request TTL
 */
final class RedisIdempotencyCache
{
    private const PREFIX = 'idempotency:';

    public function get(string $key): ?IdempotencyKey
    {
        /** @var string|null $raw */
        $raw = Cache::store('redis')->get(self::PREFIX . $key);

        if ($raw === null) {
            return null;
        }

        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return new IdempotencyKey(
            id: $data['id'],
            key: $data['key'],
            requestHash: $data['request_hash'],
            responsePayload: $data['response_payload'],
            expiresAt: isset($data['expires_at'])
                ? new DateTimeImmutable($data['expires_at'])
                : null,
            createdAt: new DateTimeImmutable($data['created_at']),
        );
    }

    public function put(string $key, IdempotencyKey $entity, int $ttlSeconds): void
    {
        $payload = json_encode([
            'id'               => $entity->id,
            'key'              => $entity->key,
            'request_hash'     => $entity->requestHash,
            'response_payload' => $entity->responsePayload,
            'expires_at'       => $entity->expiresAt?->format(DATE_ATOM),
            'created_at'       => $entity->createdAt->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR);

        Cache::store('redis')->put(self::PREFIX . $key, $payload, $ttlSeconds);
    }

    public function has(string $key): bool
    {
        return Cache::store('redis')->has(self::PREFIX . $key);
    }

    public function forget(string $key): void
    {
        Cache::store('redis')->forget(self::PREFIX . $key);
    }
}
