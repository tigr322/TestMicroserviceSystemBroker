<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Notification\Entities\Notification;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\NotificationModel;
use DateTimeImmutable;

final class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    public function findById(int $id): ?Notification
    {
        $model = NotificationModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /** @return Notification[] */
    public function findByRecipientId(int $recipientId): array
    {
        return NotificationModel::where('recipient_id', $recipientId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (NotificationModel $m) => $this->toDomain($m))
            ->all();
    }

    public function save(Notification $notification): Notification
    {
        $model = NotificationModel::updateOrCreate(
            ['id' => $notification->id],
            [
                'recipient_id'       => $notification->recipientId,
                'channel'            => $notification->channel->value,
                'priority'           => $notification->priority->value,
                'message'            => $notification->message,
                'status'             => $notification->status->value,
                'retry_count'        => $notification->retryCount,
                'provider_message_id'=> $notification->providerMessageId,
                'idempotency_key'    => $notification->idempotencyKey,
            ]
        );

        return $this->toDomain($model);
    }

    public function updateStatus(int $id, NotificationStatus $status, ?string $providerMessageId = null): void
    {
        $update = ['status' => $status->value];

        if ($providerMessageId !== null) {
            $update['provider_message_id'] = $providerMessageId;
        }

        NotificationModel::where('id', $id)->update($update);
    }

    public function incrementRetryCount(int $id): void
    {
        NotificationModel::where('id', $id)->increment('retry_count');
    }

    private function toDomain(NotificationModel $model): Notification
    {
        return new Notification(
            id: $model->id,
            recipientId: $model->recipient_id,
            channel: $model->channel,
            priority: $model->priority,
            message: $model->message,
            status: $model->status,
            retryCount: $model->retry_count,
            providerMessageId: $model->provider_message_id,
            idempotencyKey: $model->idempotency_key,
            createdAt: DateTimeImmutable::createFromMutable($model->created_at->toDateTime()),
            updatedAt: $model->updated_at
                ? DateTimeImmutable::createFromMutable($model->updated_at->toDateTime())
                : null,
        );
    }
}
