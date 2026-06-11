<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use OpenApi\Attributes as OA;

/**
 * Top-level OpenAPI annotations — no actual HTTP handler here.
 * l5-swagger picks these up during document generation.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'Notification Microservice API',
    description: <<<'MD'
    Cloud-native Notification Service for high-volume SMS and Email delivery.

    ## Features
    - **Bulk dispatch** — send to thousands of recipients in a single request
    - **Priority queues** — `high`, `normal`, `low` processed in strict order
    - **Idempotency** — duplicate requests are safely deduplicated via Redis + PostgreSQL
    - **Retry with back-off** — temporary failures retried at 10 s → 30 s → 60 s
    - **Delivery tracking** — full status history per recipient

    ## Queue Flow
    ```
    POST /api/notifications/send
        → Validation
        → Idempotency Check (Redis / PostgreSQL)
        → Save Notifications (PostgreSQL, status=queued)
        → Publish to RabbitMQ (priority queue)
        → Worker picks up job
        → Mark status=sent
        → Call mock provider
        → success  → status=delivered
        → temp err → retry (back-off) → ... → delivered | failed
        → perm err → status=failed (no retry)
    ```
    MD,
    contact: new OA\Contact(name: 'Notification Team', email: 'notifications@example.com'),
    license: new OA\License(name: 'MIT')
)]
#[OA\Server(url: 'http://localhost:8080', description: 'Local Docker environment')]
#[OA\Server(url: 'https://api.example.com', description: 'Production')]
final class SwaggerController {}
