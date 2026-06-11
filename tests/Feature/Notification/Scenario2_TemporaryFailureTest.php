<?php

declare(strict_types=1);

/**
 * Scenario 2 — Temporary provider failure → retry → eventual delivery
 *
 * The mock provider fails temporarily on the first two attempts,
 * then succeeds on the third.
 *
 * We drive the job directly (bypassing the queue) so we can assert
 * retry_count increments and final status without needing back-off delays.
 */

use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\NotificationModel;
use App\Infrastructure\Providers\Notification\NotificationProviderFactory;
use App\Infrastructure\Providers\Notification\NotificationProviderInterface;
use App\Infrastructure\Providers\Notification\ProviderResult;
use App\Infrastructure\Providers\Notification\TemporaryProviderException;
use App\Jobs\ProcessNotificationJob;

describe('Scenario 2 — Temporary failure with retry', function (): void {

    it('increments retry_count on temporary failures and eventually delivers the notification', function (): void {
        // Arrange: provider returns temp failure on calls 1 & 2, succeeds on call 3
        $callCount = 0;

        $this->app->bind(NotificationProviderFactory::class, function () use (&$callCount) {
            $factory = Mockery::mock(NotificationProviderFactory::class);

            $factory->shouldReceive('make')
                ->andReturnUsing(function () use (&$callCount): NotificationProviderInterface {
                    ++$callCount;

                    if ($callCount <= 2) {
                        $attempt = $callCount;

                        return new class ($attempt) implements NotificationProviderInterface {
                            public function __construct(private readonly int $attempt) {}

                            public function send(\App\Domain\Notification\Entities\Notification $n): never
                            {
                                throw new TemporaryProviderException("Temp failure on attempt {$this->attempt}");
                            }
                        };
                    }

                    return new class implements NotificationProviderInterface {
                        public function send(\App\Domain\Notification\Entities\Notification $n): ProviderResult
                        {
                            return new ProviderResult('msg_retry_success', 'MockProvider');
                        }
                    };
                });

            return $factory;
        });

        // Create the notification record directly
        $model = NotificationModel::create([
            'recipient_id' => 99,
            'channel'      => 'sms',
            'priority'     => 'normal',
            'message'      => 'Retry scenario test',
            'status'       => NotificationStatus::Queued->value,
            'retry_count'  => 0,
        ]);

        $repository = app(NotificationRepositoryInterface::class);
        $factory    = app(NotificationProviderFactory::class);

        // Attempt 1 — temporary failure
        try {
            (new ProcessNotificationJob($model->id))->handle($repository, $factory);
        } catch (TemporaryProviderException) {}

        $model->refresh();
        expect($model->status->value)->toBe(NotificationStatus::Queued->value)
            ->and($model->retry_count)->toBe(1);

        // Attempt 2 — temporary failure again
        try {
            (new ProcessNotificationJob($model->id))->handle($repository, $factory);
        } catch (TemporaryProviderException) {}

        $model->refresh();
        expect($model->retry_count)->toBe(2);

        // Attempt 3 — success
        (new ProcessNotificationJob($model->id))->handle($repository, $factory);

        $model->refresh();
        expect($model->status->value)->toBe(NotificationStatus::Delivered->value)
            ->and($model->provider_message_id)->toBe('msg_retry_success')
            ->and($model->retry_count)->toBe(2); // not incremented on success
    });
});
