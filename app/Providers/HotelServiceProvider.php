<?php

declare(strict_types=1);

namespace App\Providers;

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
use Illuminate\Support\ServiceProvider;

/**
 * The wiring: the one place that chooses the real (internal) classes behind each public contract.
 * To switch to SMS, or to another store, only this file changes.
 */
final class HotelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReservationStore::class, EloquentReservationStore::class);
        $this->app->bind(RoomCatalog::class, EloquentRoomCatalog::class);
        $this->app->singleton(NotificationSender::class, EmailNotificationSender::class); // switch to SMS: change this one line
        $this->app->bind(PaymentService::class, SimulatedPaymentService::class);
        $this->app->bind(Pause::class, NullPause::class);
    }
}
