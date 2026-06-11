<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources;

use App\Infrastructure\Persistence\Eloquent\Models\NotificationModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationModel
 */
final class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'channel'             => $this->channel->value,
            'priority'            => $this->priority->value,
            'status'              => $this->status->value,
            'message'             => $this->message,
            'retry_count'         => $this->retry_count,
            'provider_message_id' => $this->provider_message_id,
            'created_at'          => $this->created_at->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
