<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\NotificationModel;
use App\Presentation\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

final class SubscriberController
{
    /**
     * List all notifications for a given subscriber/recipient.
     */
    #[OA\Get(
        path: '/api/subscribers/{id}/notifications',
        summary: 'Get notifications for a subscriber',
        description: 'Returns all notifications dispatched to the given recipient, ordered by most recent first.',
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Recipient / subscriber ID',
                schema: new OA\Schema(type: 'integer', example: 42)
            ),
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                description: 'Filter by status',
                schema: new OA\Schema(type: 'string', enum: ['queued', 'sent', 'delivered', 'failed'])
            ),
            new OA\Parameter(
                name: 'channel',
                in: 'query',
                required: false,
                description: 'Filter by channel',
                schema: new OA\Schema(type: 'string', enum: ['email', 'sms'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of notifications',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 100),
                            new OA\Property(property: 'channel', type: 'string', example: 'sms'),
                            new OA\Property(property: 'priority', type: 'string', example: 'high'),
                            new OA\Property(property: 'status', type: 'string', example: 'delivered'),
                            new OA\Property(property: 'message', type: 'string', example: 'Your order has been shipped'),
                            new OA\Property(property: 'retry_count', type: 'integer', example: 0),
                            new OA\Property(property: 'provider_message_id', type: 'string', nullable: true, example: 'sms_abc123'),
                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
                        ]
                    )
                )
            ),
        ]
    )]
    public function notifications(Request $request, int $id): AnonymousResourceCollection
    {
        $query = NotificationModel::where('recipient_id', $id)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->query('channel'));
        }

        return NotificationResource::collection($query->get());
    }
}
