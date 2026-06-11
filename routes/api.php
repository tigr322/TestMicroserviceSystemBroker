<?php

declare(strict_types=1);

use App\Presentation\Http\Controllers\NotificationController;
use App\Presentation\Http\Controllers\SubscriberController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notification Microservice — API Routes
|--------------------------------------------------------------------------
|
| All routes are stateless (no session/cookie auth).
| Authentication can be added via Laravel Sanctum / Passport in a future iteration.
|
*/

Route::prefix('notifications')->group(function (): void {
    Route::post('/send', [NotificationController::class, 'send'])
        ->name('notifications.send');
});

Route::prefix('subscribers')->group(function (): void {
    Route::get('/{id}/notifications', [SubscriberController::class, 'notifications'])
        ->whereNumber('id')
        ->name('subscribers.notifications');
});
