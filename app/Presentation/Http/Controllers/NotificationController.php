<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Application\Services\NotificationService;
use App\Presentation\Http\Requests\SendNotificationRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Notifications', description: 'Bulk notification dispatch and delivery tracking')]
final class NotificationController
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Enqueue a bulk notification for one or more recipients.
     */
    #[OA\Post(
        path: '/api/notifications/send',
        summary: 'Send bulk notifications',
        description: 'Accepts a bulk notification request and enqueues individual jobs per recipient. Idempotency is enforced via the optional idempotency_key field.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['channel', 'priority', 'message', 'recipient_ids'],
                properties: [
                    new OA\Property(property: 'channel', type: 'string', enum: ['email', 'sms'], example: 'email'),
                    new OA\Property(property: 'priority', type: 'string', enum: ['high', 'normal', 'low'], example: 'high'),
                    new OA\Property(property: 'message', type: 'string', example: 'Your verification code is 1234'),
                    new OA\Property(
                        property: 'recipient_ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3, 4]
                    ),
                    new OA\Property(property: 'idempotency_key', type: 'string', nullable: true, example: 'unique-request-id-abc123'),
                ]
            )
        ),
        tags: ['Notifications'],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Notifications accepted and queued',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'notification_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [101, 102, 103]),
                        new OA\Property(property: 'deduplicated', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: '4 notification(s) queued successfully.'),
                    ]
                )
            ),
            new OA\Response(
                response: 200,
                description: 'Duplicate request — cached response returned',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'notification_ids', type: 'array', items: new OA\Items(type: 'integer')),
                        new OA\Property(property: 'deduplicated', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Duplicate request — original response returned.'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The channel field is required.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function send(SendNotificationRequest $request): JsonResponse
    {
        $result = $this->notificationService->send($request->toDTO());

        $statusCode = $result->deduplicated ? 200 : 202;

        return response()->json($result->toArray(), $statusCode);
    }
}
