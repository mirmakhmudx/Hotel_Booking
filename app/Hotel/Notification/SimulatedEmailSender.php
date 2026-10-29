<?php

declare(strict_types=1);

namespace App\Hotel\Notification;

use App\Models\Reservation;

/** Simulated: sends nothing and needs no network, it only counts confirmations. */
final class SimulatedEmailSender implements EmailSender
{
    private int $sent = 0;

    public function sendConfirmation(Reservation $reservation): void
    {
        $this->sent++;
    }

    public function sent(): int
    {
        return $this->sent;
    }
}
