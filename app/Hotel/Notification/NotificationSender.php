<?php

declare(strict_types=1);

namespace App\Hotel\Notification;

use App\Models\Reservation;

/**
 * What BookingService needs: "tell the guest".
 * How the message travels (email, SMS, ...) is not its business.
 * An implementation throws when the message cannot be sent.
 */
interface NotificationSender
{
    public function sendConfirmation(Reservation $reservation): void;
}
