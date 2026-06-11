<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature/Notification');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeNotificationStatus', function (string $status) {
    return $this->toBe($status);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Helper: POST to /api/notifications/send and return the response.
 */
function sendNotification(array $payload): Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/notifications/send', $payload);
}
