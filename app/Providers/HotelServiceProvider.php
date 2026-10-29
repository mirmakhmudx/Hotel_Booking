<?php

declare(strict_types=1);

namespace App\Providers;

use App\Hotel\Notification\EmailNotificationSender;
use App\Hotel\Notification\NotificationSender;
use App\Hotel\Persistence\EloquentReservationStore;
use App\Hotel\Persistence\EloquentRoomCatalog;
use App\Hotel\Persistence\ReservationStore;
use App\Hotel\Persistence\RoomCatalog;
use App\Hotel\Support\NullPause;
use App\Hotel\Support\Pause;
use Illuminate\Support\ServiceProvider;

/** The composition root: the one place that chooses the real classes behind each interface. */
final class HotelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReservationStore::class, EloquentReservationStore::class);
        $this->app->bind(RoomCatalog::class, EloquentRoomCatalog::class);
        $this->app->singleton(NotificationSender::class, EmailNotificationSender::class); // switch to SMS: change this one line
        $this->app->bind(Pause::class, NullPause::class);
    }
}
