<?php

declare(strict_types=1);

/**
 * Scenario 3 — Permanent provider failure
 *
 * Expected: status becomes `failed` immediately, no retry is attempted.
 *
 * PermanentProviderException must NOT propagate out of the job —
 * the job absorbs it, marks the notification as failed, and deletes itself
 * from the queue. retry_count stays at 0.
 */

use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\NotificationModel;
use App\Infrastructure\Providers\Notification\NotificationProviderFactory;
use App\Infrastructure\Providers\Notification\NotificationProviderInterface;
use App\Infrastructure\Providers\Notification\PermanentProviderException;
use App\Jobs\ProcessNotificationJob;

describe('Scenario 3 — Permanent failure', function (): void {

    it('marks the notification as failed immediately without incrementing retry_count', function (): void {
        // Arrange — provider always throws a permanent exception
        $this->app->bind(NotificationProviderFactory::class, function () {
            $factory = Mockery::mock(NotificationProviderFactory::class);
            $factory->shouldReceive('make')
                ->andReturn(new class implements NotificationProviderInterface {
                    public function send(\App\Domain\Notification\Entities\Notification $n): never
                    {
                        throw new PermanentProviderException('Hard bounce — address invalid');
                    }
                });

            return $factory;
        });

        $model = NotificationModel::create([
            'recipient_id' => 77,
            'channel'      => 'email',
            'priority'     => 'low',
            'message'      => 'Permanent failure test',
            'status'       => NotificationStatus::Queued->value,
            'retry_count'  => 0,
        ]);

        $repository = app(NotificationRepositoryInterface::class);
        $factory    = app(NotificationProviderFactory::class);

        // Act — the job should NOT throw; it absorbs PermanentProviderException
        $job = new ProcessNotificationJob($model->id);
        $job->handle($repository, $factory);

        // Assert
        $model->refresh();
        expect($model->status->value)->toBe(NotificationStatus::Failed->value)
            ->and($model->retry_count)->toBe(0);
    });
});
