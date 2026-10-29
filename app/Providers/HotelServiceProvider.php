<?php

declare(strict_types=1);

namespace App\Providers;

use App\Hotel\Notification\EmailSender;
use App\Hotel\Notification\SimulatedEmailSender;
use App\Hotel\Persistence\EloquentReservationStore;
use App\Hotel\Persistence\EloquentRoomCatalog;
use App\Hotel\Persistence\ReservationStore;
use App\Hotel\Persistence\RoomCatalog;
use App\Hotel\Support\NullPause;
use App\Hotel\Support\Pause;
use Illuminate\Support\ServiceProvider;

/** Tells Laravel which implementation to use for each interface. */
final class HotelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReservationStore::class, EloquentReservationStore::class);
        $this->app->bind(RoomCatalog::class, EloquentRoomCatalog::class);
        $this->app->singleton(EmailSender::class, SimulatedEmailSender::class);
        $this->app->bind(Pause::class, NullPause::class);
    }
}
