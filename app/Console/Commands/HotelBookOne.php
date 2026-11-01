<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Hotel\Notifications\Internal\EmailNotificationSender;
use App\Hotel\Reservations\BookingService;
use App\Hotel\Reservations\ConcurrencyStrategy;
use App\Hotel\Reservations\DateRange;
use App\Hotel\Reservations\Guest;
use App\Hotel\Reservations\Internal\NoDoubleBookingConstraint;
use App\Hotel\Reservations\TooMuchContention;
use App\Hotel\Rooms\Room;
use App\Hotel\Support\FiberPause;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Throwable;

/** Used by hotel:race and the tests: ONE booking request in ONE process. Not meant for people. */
final class HotelBookOne extends Command
{
    protected $signature = 'hotel:book-one {guest} {room} {checkIn} {checkOut} {strategy} {pause=0}';

    protected $description = 'P09 helper: one booking request in its own process (waits for "go" on stdin)';

    protected $hidden = true;

    public function handle(): int
    {
        $guest = Guest::query()->where('name', $this->argument('guest'))->firstOrFail();
        $room = Room::query()->where('number', $this->argument('room'))->firstOrFail();
        $stay = new DateRange((string) $this->argument('checkIn'), (string) $this->argument('checkOut'));

        $service = app()->makeWith(BookingService::class, [
            'notifications' => new EmailNotificationSender(),
            'pause' => new FiberPause((int) $this->argument('pause')), // outside a Fiber this is a plain sleep
            'strategy' => ConcurrencyStrategy::from((string) $this->argument('strategy')),
        ]);

        $this->line('READY');
        fflush(STDOUT);
        fgets(STDIN); // wait for the starting gun

        try {
            $answer = (string) $service->reserve($guest, $room, $stay);
        } catch (TooMuchContention) {
            $answer = 'TooMuchContention';
        } catch (QueryException $failure) {
            $answer = match ((string) $failure->getCode()) {
                NoDoubleBookingConstraint::SQLSTATE => 'RefusedByDatabase',
                '40P01' => 'DeadlockVictim', // the database aborted this request: nothing was saved
                default => 'Error: ' . $failure->getMessage(),
            };
        } catch (Throwable $failure) {
            $answer = 'Error: ' . $failure->getMessage();
        }

        $this->line('RESULT ' . $answer);

        return self::SUCCESS;
    }
}
