<?php

declare(strict_types=1);

namespace App\Hotel\Notification;

use App\Models\Reservation;

/** Added in P04 without changing BookingService. Simulated, like the email sender. */
final class SmsNotificationSender implements NotificationSender
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
