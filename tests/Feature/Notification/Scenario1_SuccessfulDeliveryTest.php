<?php

declare(strict_types=1);

/**
 * Scenario 1 — Successful delivery
 *
 * Expected flow: queued → sent → delivered
 *
 * The mock provider is overridden to always succeed (successRate = 100).
 * The job is run synchronously via the 'sync' queue driver.
 */

use App\Domain\Notification\Enums\NotificationStatus;
use App\Infrastructure\Persistence\Eloquent\Models\NotificationModel;
use App\Infrastructure\Providers\Notification\NotificationProviderFactory;
use App\Infrastructure\Providers\Notification\NotificationProviderInterface;
use App\Infrastructure\Providers\Notification\ProviderResult;

describe('Scenario 1 — Successful delivery', function (): void {

    beforeEach(function (): void {
        // Use sync queue so jobs run in-process during tests
        config(['queue.default' => 'sync']);
    });

    it('transitions a notification from queued → sent → delivered when the provider succeeds', function (): void {
        // Arrange — provider always succeeds
        $this->app->bind(NotificationProviderFactory::class, function () {
            $factory = Mockery::mock(NotificationProviderFactory::class);
            $factory->shouldReceive('make')
                ->andReturn(new class implements NotificationProviderInterface {
                    public function send(\App\Domain\Notification\Entities\Notification $n): ProviderResult
                    {
                        return new ProviderResult('msg_success_001', 'MockProvider');
                    }
                });

            return $factory;
        });

        // Act
        $response = sendNotification([
            'channel'        => 'email',
            'priority'       => 'high',
            'message'        => 'Your verification code is 1234',
            'recipient_ids'  => [42],
            'idempotency_key'=> 'scenario-1-' . uniqid(),
        ]);

        // Assert HTTP response
        $response->assertStatus(202);
        $response->assertJsonStructure(['notification_ids', 'deduplicated', 'message']);
        $response->assertJsonPath('deduplicated', false);

        $notificationId = $response->json('notification_ids.0');

        // Assert DB record
        $notification = NotificationModel::findOrFail($notificationId);

        expect($notification->status->value)->toBe(NotificationStatus::Delivered->value)
            ->and($notification->provider_message_id)->toBe('msg_success_001')
            ->and($notification->retry_count)->toBe(0);
    });
});
