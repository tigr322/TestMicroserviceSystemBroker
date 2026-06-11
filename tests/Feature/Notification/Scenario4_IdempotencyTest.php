<?php

declare(strict_types=1);

/**
 * Scenario 4 — Duplicate request with same idempotency_key
 *
 * Expected: only one batch of notifications is created;
 * the second call returns the cached (deduplicated) response.
 *
 * Uses the actual Redis idempotency stack via a fake cache
 * (array driver) to keep the test hermetic.
 */

use App\Infrastructure\Persistence\Eloquent\Models\NotificationModel;
use App\Infrastructure\Providers\Notification\NotificationProviderFactory;
use App\Infrastructure\Providers\Notification\NotificationProviderInterface;
use App\Infrastructure\Providers\Notification\ProviderResult;

describe('Scenario 4 — Idempotency / deduplication', function (): void {

    beforeEach(function (): void {
        // Use array cache so Redis is not required in CI
        config([
            'cache.default'  => 'array',
            'queue.default'  => 'sync',
        ]);

        // Provider always succeeds so the sync queue doesn't contaminate the test
        $this->app->bind(NotificationProviderFactory::class, function () {
            $factory = Mockery::mock(NotificationProviderFactory::class);
            $factory->shouldReceive('make')
                ->andReturn(new class implements NotificationProviderInterface {
                    public function send(\App\Domain\Notification\Entities\Notification $n): ProviderResult
                    {
                        return new ProviderResult('msg_idem_001', 'MockProvider');
                    }
                });

            return $factory;
        });
    });

    it('creates only one batch for the same idempotency_key', function (): void {
        $key     = 'dedup-test-' . uniqid();
        $payload = [
            'channel'        => 'sms',
            'priority'       => 'normal',
            'message'        => 'Idempotency test message',
            'recipient_ids'  => [10, 11, 12],
            'idempotency_key'=> $key,
        ];

        // First request — should be accepted (202)
        $first = sendNotification($payload);
        $first->assertStatus(202);
        $first->assertJsonPath('deduplicated', false);

        $firstIds = $first->json('notification_ids');

        // Second request — same idempotency_key — should be deduplicated (200)
        $second = sendNotification($payload);
        $second->assertStatus(200);
        $second->assertJsonPath('deduplicated', true);

        $secondIds = $second->json('notification_ids');

        // IDs must be identical — no new rows created
        expect($secondIds)->toBe($firstIds);

        // Exactly 3 notifications in DB (one per recipient)
        expect(NotificationModel::count())->toBe(3);
    });

    it('treats requests with different idempotency_keys as independent batches', function (): void {
        $payload1 = [
            'channel'        => 'email',
            'priority'       => 'low',
            'message'        => 'First batch',
            'recipient_ids'  => [20],
            'idempotency_key'=> 'key-a-' . uniqid(),
        ];

        $payload2 = [
            'channel'        => 'email',
            'priority'       => 'low',
            'message'        => 'Second batch',
            'recipient_ids'  => [20],
            'idempotency_key'=> 'key-b-' . uniqid(),
        ];

        sendNotification($payload1)->assertStatus(202);
        sendNotification($payload2)->assertStatus(202);

        expect(NotificationModel::count())->toBe(2);
    });
});
