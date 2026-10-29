<?php

declare(strict_types=1);

namespace App\Hotel\Notifications\Internal;

use App\Hotel\Notifications\Confirmation;
use App\Hotel\Notifications\NotificationSender;

/** Added in P04 without changing BookingService. Simulated, like the email sender. */
final class SmsNotificationSender implements NotificationSender
{
    private int $sent = 0;

    public function sendConfirmation(Confirmation $confirmation): void
    {
        $this->sent++;
    }

    public function sent(): int
    {
        return $this->sent;
    }
}
