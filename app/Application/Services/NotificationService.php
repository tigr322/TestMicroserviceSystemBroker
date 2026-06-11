<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\NotificationResultDTO;
use App\Application\DTOs\SendNotificationDTO;
use App\Domain\Idempotency\Repositories\IdempotencyKeyRepositoryInterface;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\NotificationModel;
use App\Jobs\ProcessNotificationJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class NotificationService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly IdempotencyKeyRepositoryInterface $idempotencyKeys,
        private readonly int $idempotencyTtl,
    ) {}

    /**
     * Accept a bulk send request, deduplicate, persist, and enqueue.
     *
     * Returns early (with cached payload) when the idempotency_key has already been seen.
     */
    public function send(SendNotificationDTO $dto): NotificationResultDTO
    {
        // ── 1. Idempotency check ─────────────────────────────────────────────
        if ($dto->idempotencyKey !== null) {
            $existing = $this->idempotencyKeys->findByKey($dto->idempotencyKey);

            if ($existing !== null && ! $existing->isExpired()) {
                Log::info('Idempotency hit — returning cached response.', [
                    'key' => $dto->idempotencyKey,
                ]);

                return new NotificationResultDTO(
                    notificationIds: $existing->responsePayload['notification_ids'],
                    deduplicated: true,
                    message: 'Duplicate request — original response returned.',
                );
            }
        }

        // ── 2. Persist & enqueue — wrapped in a DB transaction ───────────────
        $notificationIds = DB::transaction(function () use ($dto): array {
            $ids = [];

            foreach ($dto->recipientIds as $recipientId) {
                $model = NotificationModel::create([
                    'recipient_id'    => $recipientId,
                    'channel'         => $dto->channel->value,
                    'priority'        => $dto->priority->value,
                    'message'         => $dto->message,
                    'status'          => NotificationStatus::Queued->value,
                    'retry_count'     => 0,
                    'idempotency_key' => $dto->idempotencyKey,
                ]);

                $ids[] = $model->id;
            }

            return $ids;
        });

        // ── 3. Dispatch jobs to priority-specific queues ─────────────────────
        foreach ($notificationIds as $notificationId) {
            ProcessNotificationJob::dispatch($notificationId)
                ->onQueue($dto->priority->queue());
        }

        Log::info('Notifications enqueued.', [
            'ids'      => $notificationIds,
            'channel'  => $dto->channel->value,
            'priority' => $dto->priority->value,
            'queue'    => $dto->priority->queue(),
        ]);

        $payload = [
            'notification_ids' => $notificationIds,
            'deduplicated'     => false,
            'message'          => count($notificationIds) . ' notification(s) queued successfully.',
        ];

        // ── 4. Store idempotency record ──────────────────────────────────────
        if ($dto->idempotencyKey !== null) {
            $this->idempotencyKeys->save(
                key: $dto->idempotencyKey,
                requestHash: $dto->requestHash(),
                responsePayload: $payload,
                ttlSeconds: $this->idempotencyTtl,
            );
        }

        return new NotificationResultDTO(
            notificationIds: $notificationIds,
            deduplicated: false,
            message: $payload['message'],
        );
    }
}
