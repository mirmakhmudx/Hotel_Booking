<?php

declare(strict_types=1);

namespace App\Hotel\Notification;

use App\Models\Reservation;

/** Sends the confirmation of a saved reservation. */
interface EmailSender
{
    public function sendConfirmation(Reservation $reservation): void;
}
