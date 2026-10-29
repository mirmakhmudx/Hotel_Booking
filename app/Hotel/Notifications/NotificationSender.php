<?php

declare(strict_types=1);

namespace App\Hotel\Notifications;

/**
 * Notifications' public contract: "tell the guest".
 * How the message travels (email, SMS, ...) is its own business.
 * An implementation throws when the message cannot be sent.
 */
interface NotificationSender
{
    public function sendConfirmation(Confirmation $confirmation): void;
}
