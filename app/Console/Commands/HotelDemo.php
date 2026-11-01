<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Hotel\Reservations\BookingService;
use App\Hotel\Reservations\ConcurrencyStrategy;
use App\Hotel\Reservations\Internal\NoDoubleBookingConstraint;
use App\Hotel\Reservations\DateRange;
use App\Hotel\Notifications\Internal\EmailNotificationSender;
use App\Hotel\Reservations\ReservationStore;
use App\Hotel\Support\FiberPause;
use App\Hotel\Support\FiberScheduler;
use App\Hotel\Support\NullPause;
use App\Hotel\Support\Pause;
use App\Hotel\Reservations\Guest;
use App\Hotel\Reservations\Reservation;
use App\Hotel\Rooms\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

final class HotelDemo extends Command
{
    protected $signature = 'hotel:demo {--race : Two guests book the same room at the same moment}';

    protected $description = 'P03 demo: the requirement examples, or the version 1 double-booking race with Fibers (clears all reservations first). The real, fixed race is hotel:race.';

    public function __construct(private readonly ReservationStore $store)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        Reservation::truncate(); // fresh start: ids begin at R101 again

        if (! $this->option('race')) {
            return $this->runDemo();
        }

        // P09: this demo shows the UNPROTECTED version 1, so the database safety net is lifted for the run.
        $hadConstraint = NoDoubleBookingConstraint::exists();
        NoDoubleBookingConstraint::drop();

        try {
            return $this->runRace();
        } finally {
            if ($hadConstraint) {
                Reservation::truncate(); // the demo left a double booking; the constraint cannot be added with it
                NoDoubleBookingConstraint::add();
            }
        }
    }

    private function runDemo(): int
    {
        $email = new EmailNotificationSender();
        $service = $this->service($email, new NullPause());
        $r7 = $this->room('R7');
        $r8 = $this->room('R8');
        $maya = $this->guest('Maya');
        $omar = $this->guest('Omar');
        $nora = $this->guest('Nora');

        $this->line('Free rooms 12-14 Nov: ' . $this->names($service->findFreeRooms($this->nov(12, 14))));
        $this->line('Maya books R7 12-14 Nov: ' . $service->reserve($maya, $r7, $this->nov(12, 14)));
        $this->line('Omar books R7 13-15 Nov: ' . $service->reserve($omar, $r7, $this->nov(13, 15)));
        $this->line('Nora books R7 14-16 Nov: ' . $service->reserve($nora, $r7, $this->nov(14, 16)));
        $this->line('Omar books R8 15-15 Nov: ' . $service->reserve($omar, $r8, $this->nov(15, 15)));
        $this->line('Free rooms 12-14 Nov: ' . $this->names($service->findFreeRooms($this->nov(12, 14))));
        $this->line('Maya cancels R101: ' . $this->boolText($service->cancel('R101')));
        $this->line('Free rooms 12-14 Nov: ' . $this->names($service->findFreeRooms($this->nov(12, 14))));
        $this->line('Confirmation emails sent: ' . $email->sent());

        return self::SUCCESS;
    }

    private function runRace(): int
    {
        // Each request now spends half a second between checking and saving.
        $service = $this->service(new EmailNotificationSender(), new FiberPause(500), ConcurrencyStrategy::Unprotected);
        $r7 = $this->room('R7');
        $maya = $this->guest('Maya');
        $omar = $this->guest('Omar');
        $stay = $this->nov(12, 14);
        $results = [];

        $this->line('Maya and Omar both book R7 for 12-14 Nov at the same moment...');

        $scheduler = new FiberScheduler();
        $scheduler->spawn(0, function () use (&$results, $service, $maya, $r7, $stay): void {
            $results['Maya'] = $service->reserve($maya, $r7, $stay);
        });
        $scheduler->spawn(100, function () use (&$results, $service, $omar, $r7, $stay): void { // Omar clicks a moment after Maya
            $results['Omar'] = $service->reserve($omar, $r7, $stay);
        });
        $scheduler->run();

        $this->line("Maya: {$results['Maya']}");
        $this->line("Omar: {$results['Omar']}");

        $confirmed = $this->store->countConfirmed($r7, $stay);
        $this->line("Confirmed reservations for R7 on 12-14 Nov: {$confirmed}");
        $this->line($confirmed > 1
            ? 'Double booking! Both requests checked before either one saved. (Unprotected version 1; the fix: php artisan hotel:race)'
            : 'Only one guest has R7.');

        return self::SUCCESS;
    }

    private function service(EmailNotificationSender $email, Pause $pause, ?ConcurrencyStrategy $strategy = null): BookingService
    {
        $arguments = ['notifications' => $email, 'pause' => $pause];

        if ($strategy !== null) {
            $arguments['strategy'] = $strategy;
        }

        return app()->makeWith(BookingService::class, $arguments);
    }

    private function nov(int $checkIn, int $checkOut): DateRange
    {
        return new DateRange(sprintf('2026-11-%02d', $checkIn), sprintf('2026-11-%02d', $checkOut));
    }

    private function room(string $number): Room
    {
        return Room::query()->where('number', $number)->firstOrFail(); // run: php artisan migrate --seed
    }

    private function guest(string $name): Guest
    {
        return Guest::query()->firstOrCreate(
            ['email' => strtolower($name) . '@example.com'],
            ['name' => $name],
        );
    }

    /** @param Collection<int, Room> $rooms */
    private function names(Collection $rooms): string
    {
        return $rooms->pluck('number')->implode(', ');
    }

    private function boolText(bool $value): string
    {
        return $value ? 'True' : 'False';
    }
}
