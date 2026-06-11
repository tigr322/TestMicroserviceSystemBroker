<?php

declare(strict_types=1);

/**
 * Scenario 5 — Priority processing
 *
 * Expected: high-priority notifications are dispatched to `notifications.high`,
 * normal to `notifications.normal`, and low to `notifications.low`.
 *
 * Workers consume queues in high → normal → low order, guaranteeing
 * high-priority jobs are always processed before lower-priority ones.
 *
 * This test asserts:
 *   1. Each priority level dispatches to the correct queue name.
 *   2. The Priority enum weight ordering matches the worker consumption order.
 *   3. Both high and low notifications can coexist in their respective queues.
 */

use App\Domain\Notification\Enums\Priority;
use App\Infrastructure\Persistence\Eloquent\Models\NotificationModel;
use App\Jobs\ProcessNotificationJob;
use Illuminate\Support\Facades\Queue;

describe('Scenario 5 — Priority queue routing', function (): void {

    it('dispatches each notification to the correct priority queue', function (): void {
        $cases = [
            ['priority' => 'high',   'queue' => 'notifications.high'],
            ['priority' => 'normal', 'queue' => 'notifications.normal'],
            ['priority' => 'low',    'queue' => 'notifications.low'],
        ];

        foreach ($cases as ['priority' => $priority, 'queue' => $expectedQueue]) {
            // Fresh fake for each iteration so previous pushes don't bleed over
            Queue::fake();

            sendNotification([
                'channel'        => 'email',
                'priority'       => $priority,
                'message'        => "Test {$priority} priority message",
                'recipient_ids'  => [1],
                'idempotency_key'=> "prio-{$priority}-" . uniqid(),
            ])->assertStatus(202);

            Queue::assertPushedOn($expectedQueue, ProcessNotificationJob::class);
        }
    });

    it('Priority enum weights enforce the correct processing order', function (): void {
        expect(Priority::High->weight())
            ->toBeGreaterThan(Priority::Normal->weight())
            ->and(Priority::Normal->weight())
            ->toBeGreaterThan(Priority::Low->weight());
    });

    it('high-priority and low-priority notifications land on their respective queues when dispatched in reverse order', function (): void {
        Queue::fake();

        // Intentionally dispatch low first — should still end up on its own queue
        sendNotification([
            'channel'        => 'sms',
            'priority'       => 'low',
            'message'        => 'Marketing bulk message',
            'recipient_ids'  => [50],
            'idempotency_key'=> 'prio5-low-' . uniqid(),
        ])->assertStatus(202);

        sendNotification([
            'channel'        => 'sms',
            'priority'       => 'high',
            'message'        => 'OTP: 9876',
            'recipient_ids'  => [51],
            'idempotency_key'=> 'prio5-high-' . uniqid(),
        ])->assertStatus(202);

        // Both records in DB
        expect(NotificationModel::where('priority', 'high')->count())->toBe(1)
            ->and(NotificationModel::where('priority', 'low')->count())->toBe(1);

        // Each job dispatched to its correct queue
        Queue::assertPushedOn('notifications.high', ProcessNotificationJob::class);
        Queue::assertPushedOn('notifications.low', ProcessNotificationJob::class);

        // Total of 2 jobs dispatched
        Queue::assertCount(2);
    });
});
