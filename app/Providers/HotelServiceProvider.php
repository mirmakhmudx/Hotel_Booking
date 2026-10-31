<?php

declare(strict_types=1);

namespace App\Providers;

use App\Api\AvailabilityCache;
use App\Api\CountingReservationStore;
use App\Api\KeyValueStore;
use App\Api\LaravelKeyValueStore;
use App\Api\Stats;
use App\Hotel\Notifications\Internal\EmailNotificationSender;
use App\Hotel\Notifications\NotificationSender;
use App\Hotel\Payments\Internal\SimulatedPaymentService;
use App\Hotel\Payments\PaymentService;
use App\Hotel\Reservations\Internal\EloquentReservationStore;
use App\Hotel\Reservations\ReservationStore;
use App\Hotel\Rooms\Internal\EloquentRoomCatalog;
use App\Hotel\Rooms\RoomCatalog;
use App\Hotel\Support\NullPause;
use App\Hotel\Support\Pause;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;

/**
 * The wiring: the one place that chooses the real (internal) classes behind each public contract.
 * To switch to SMS, or to another store, only this file changes.
 */
final class HotelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // P08: every question to the database is counted for GET /stats (the decorator wraps the real store).
        $this->app->bind(ReservationStore::class, fn ($app): ReservationStore => new CountingReservationStore(
            $app->make(EloquentReservationStore::class),
            $app->make(Stats::class),
        ));
        $this->app->bind(RoomCatalog::class, EloquentRoomCatalog::class);
        $this->app->singleton(NotificationSender::class, EmailNotificationSender::class); // switch to SMS: change this one line
        $this->app->bind(PaymentService::class, SimulatedPaymentService::class);
        $this->app->bind(Pause::class, NullPause::class);

        // P08: the cache and the counters live in Laravel's cache store (CACHE_STORE in .env).
        $this->app->singleton(KeyValueStore::class, fn ($app): KeyValueStore => new LaravelKeyValueStore($app->make(CacheRepository::class)));
        $this->app->singleton(Stats::class);
        $this->app->singleton(AvailabilityCache::class);
    }
}
