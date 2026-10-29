<?php

declare(strict_types=1);

namespace App\Hotel\Notifications\Internal;

use App\Hotel\Notifications\Confirmation;
use App\Hotel\Notifications\NotificationSender;

/** Simulated: counts the emails it would send and needs no network. */
final class EmailNotificationSender implements NotificationSender
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
