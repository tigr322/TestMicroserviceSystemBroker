<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Services\NotificationService;
use App\Domain\Idempotency\Repositories\IdempotencyKeyRepositoryInterface;
use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Infrastructure\Cache\RedisIdempotencyCache;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentIdempotencyKeyRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentNotificationRepository;
use App\Infrastructure\Providers\Notification\NotificationProviderFactory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── Repository bindings (interface → implementation) ─────────────
        $this->app->bind(
            NotificationRepositoryInterface::class,
            EloquentNotificationRepository::class,
        );

        $this->app->bind(
            IdempotencyKeyRepositoryInterface::class,
            EloquentIdempotencyKeyRepository::class,
        );

        // ── Application service ───────────────────────────────────────────
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService(
                notifications: $app->make(NotificationRepositoryInterface::class),
                idempotencyKeys: $app->make(IdempotencyKeyRepositoryInterface::class),
                idempotencyTtl: (int) config('notification.idempotency_ttl', 86400),
            );
        });

        // ── Infrastructure singletons ─────────────────────────────────────
        $this->app->singleton(RedisIdempotencyCache::class);
        $this->app->singleton(NotificationProviderFactory::class);
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());
    }
}
