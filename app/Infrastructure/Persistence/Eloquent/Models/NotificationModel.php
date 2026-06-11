<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Notification\Enums\Channel;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Enums\Priority;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence model for the notifications table.
 * Intentionally kept separate from the domain Notification entity.
 *
 * @property int                 $id
 * @property int                 $recipient_id
 * @property Channel             $channel
 * @property Priority            $priority
 * @property string              $message
 * @property NotificationStatus  $status
 * @property int                 $retry_count
 * @property string|null         $provider_message_id
 * @property string|null         $idempotency_key
 * @property \Carbon\Carbon      $created_at
 * @property \Carbon\Carbon      $updated_at
 */
final class NotificationModel extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'recipient_id',
        'channel',
        'priority',
        'message',
        'status',
        'retry_count',
        'provider_message_id',
        'idempotency_key',
    ];

    protected $casts = [
        'channel'      => Channel::class,
        'priority'     => Priority::class,
        'status'       => NotificationStatus::class,
        'retry_count'  => 'integer',
        'recipient_id' => 'integer',
    ];
}
