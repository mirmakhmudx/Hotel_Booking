<?php

declare(strict_types=1);

namespace App\Hotel\Notification;

use App\Models\Reservation;

/** Simulated: counts the emails it would send and needs no network. */
final class EmailNotificationSender implements NotificationSender
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
