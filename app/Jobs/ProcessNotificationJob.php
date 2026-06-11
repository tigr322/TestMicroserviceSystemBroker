<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\NotificationModel;
use App\Infrastructure\Providers\Notification\NotificationProviderFactory;
use App\Infrastructure\Providers\Notification\PermanentProviderException;
use App\Infrastructure\Providers\Notification\TemporaryProviderException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes a single notification through the mock provider.
 *
 * Retry strategy (at-least-once delivery):
 *   Attempt 1 → immediate
 *   Attempt 2 → +10 s
 *   Attempt 3 → +30 s
 *   Attempt 4 → +60 s
 *   After 4 attempts → failed() callback → status = failed
 *
 * The job is dispatched onto the priority queue that matches its
 * notification's priority (notifications.high / .normal / .low).
 * Workers are started as:
 *   php artisan horizon  (or queue:work --queue=notifications.high,notifications.normal,notifications.low)
 * which guarantees high-priority messages are always drained first.
 */
final class ProcessNotificationJob implements ShouldQueue
{
    use Queueable; // includes Dispatchable, InteractsWithQueue, Bus\Queueable, SerializesModels

    /** Total attempts including the first try */
    public int $tries = 4;

    /**
     * Back-off delays in seconds between attempts.
     * Laravel uses these values for attempts 2, 3, 4.
     *
     * @var int[]
     */
    public array $backoff = [10, 30, 60];

    /** Do NOT release the job back to the queue on unhandled exceptions. */
    public bool $failOnTimeout = true;

    public int $timeout = 30;

    public function __construct(
        private readonly int $notificationId,
    ) {}

    public function handle(
        NotificationRepositoryInterface $repository,
        NotificationProviderFactory $factory,
    ): void {
        $notification = $repository->findById($this->notificationId);

        if ($notification === null) {
            Log::warning("ProcessNotificationJob: notification #{$this->notificationId} not found — discarding.");

            return;
        }

        // Skip already-terminal notifications (idempotent re-delivery protection)
        if ($notification->status->isTerminal()) {
            Log::info("ProcessNotificationJob: notification #{$this->notificationId} already terminal ({$notification->status->value}) — skipping.");

            return;
        }

        Log::info("Processing notification #{$this->notificationId}", [
            'channel'  => $notification->channel->value,
            'priority' => $notification->priority->value,
            'attempt'  => $this->attempts(),
        ]);

        // ── Mark as sent (in-flight) ──────────────────────────────────────
        $repository->updateStatus($this->notificationId, NotificationStatus::Sent);

        try {
            $provider = $factory->make($notification->channel);
            $result   = $provider->send($notification);

            // ── Success ───────────────────────────────────────────────────
            $repository->updateStatus(
                id: $this->notificationId,
                status: NotificationStatus::Delivered,
                providerMessageId: $result->messageId,
            );

            Log::info("Notification #{$this->notificationId} delivered via {$result->providerName}.", [
                'provider_message_id' => $result->messageId,
            ]);
        } catch (TemporaryProviderException $e) {
            // ── Transient failure — retry ─────────────────────────────────
            $repository->incrementRetryCount($this->notificationId);
            $repository->updateStatus($this->notificationId, NotificationStatus::Queued);

            Log::warning("Notification #{$this->notificationId} temporary failure — will retry.", [
                'attempt' => $this->attempts(),
                'error'   => $e->getMessage(),
            ]);

            // Re-throw so Laravel applies the back-off and re-queues the job
            throw $e;
        } catch (PermanentProviderException $e) {
            // ── Permanent failure — do NOT retry ─────────────────────────
            $repository->updateStatus($this->notificationId, NotificationStatus::Failed);

            Log::error("Notification #{$this->notificationId} permanently failed.", [
                'error' => $e->getMessage(),
            ]);

            // Delete the job from the queue — no retry
            $this->delete();
        }
    }

    /**
     * Called by Laravel after all retry attempts are exhausted.
     * Ensures the notification is always marked as failed in the database.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("Notification #{$this->notificationId} exhausted all retries.", [
            'error' => $exception->getMessage(),
        ]);

        /** @var NotificationRepositoryInterface $repository */
        $repository = app(NotificationRepositoryInterface::class);

        // Guard: only update if not already in a terminal state
        $model = NotificationModel::find($this->notificationId);
        if ($model && ! $model->status->isTerminal()) {
            $repository->updateStatus($this->notificationId, NotificationStatus::Failed);
        }
    }
}
