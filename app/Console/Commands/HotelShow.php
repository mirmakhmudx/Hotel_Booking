<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Hotel\Persistence\ReservationStore;
use Illuminate\Console\Command;

/** R5 · View a booking by its id. */
final class HotelShow extends Command
{
    protected $signature = 'hotel:show {code : Reservation id, for example R101}';

    protected $description = 'Show one reservation (requirement R5)';

    public function handle(ReservationStore $store): int
    {
        $reservation = $store->find((string) $this->argument('code'));

        if ($reservation === null) {
            $this->error('No reservation with that id.');

            return self::FAILURE;
        }

        $this->line(sprintf(
            '%s: %s, %s, %s, %s',
            $reservation->code,
            $reservation->guest->name,
            $reservation->room->number,
            $reservation->stay()->label(),
            $reservation->status->value,
        ));

        return self::SUCCESS;
    }
}
