<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Hotel\Notifications\Internal\EmailNotificationSender;
use App\Hotel\Reservations\BookingService;
use App\Hotel\Reservations\ConcurrencyStrategy;
use App\Hotel\Reservations\DateRange;
use App\Hotel\Reservations\Guest;
use App\Hotel\Reservations\Internal\InMemoryReservationStore;
use App\Hotel\Rooms\Internal\InMemoryRoomCatalog;
use App\Hotel\Rooms\Room;
use App\Hotel\Support\FiberPause;
use App\Hotel\Support\FiberScheduler;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SIMULATION ONLY (no database, one process, Fibers): it checks that BookingService hands the strategy
 * to the store and answers Unavailable when placeIfFree says no. It is NOT evidence that a database
 * strategy works: that is ConcurrentBookingTest.
 */
final class ConcurrencyStrategyInMemoryTest extends TestCase
{
    public function test_unprotected_simulation_double_books(): void
    {
        [$store, $answers] = $this->raceOf(ConcurrencyStrategy::Unprotected);

        $this->assertSame(2, $store->count());
        $this->assertSame(['Accepted R101', 'Accepted R102'], $answers);
    }

    #[DataProvider('protectedStrategies')]
    public function test_protected_simulation_gives_the_last_seat_to_one_guest(ConcurrencyStrategy $strategy): void
    {
        [$store, $answers] = $this->raceOf($strategy);

        $this->assertSame(1, $store->count());
        $this->assertSame(['Accepted R101', 'Unavailable'], $answers);
    }

    public static function protectedStrategies(): array
    {
        return [
            'atomic' => [ConcurrencyStrategy::AtomicInsert],
            'optimistic' => [ConcurrencyStrategy::Optimistic],
            'lock' => [ConcurrencyStrategy::RowLock],
        ];
    }

    /** @return array{0: InMemoryReservationStore, 1: list<string>} */
    private function raceOf(ConcurrencyStrategy $strategy): array
    {
        $store = new InMemoryReservationStore();
        $r7 = (new Room(['number' => 'R7', 'type' => 'Standard']))->forceFill(['id' => 7]);
        $maya = (new Guest(['name' => 'Maya', 'email' => 'maya@example.com']))->forceFill(['id' => 1]);
        $omar = (new Guest(['name' => 'Omar', 'email' => 'omar@example.com']))->forceFill(['id' => 2]);
        $service = new BookingService(new InMemoryRoomCatalog([$r7]), $store, new EmailNotificationSender(), new FiberPause(50), $strategy);
        $stay = new DateRange('2026-11-12', '2026-11-14');
        $answers = [];

        $scheduler = new FiberScheduler();
        $scheduler->spawn(0, function () use ($service, $maya, $r7, $stay, &$answers): void {
            $answers[0] = (string) $service->reserve($maya, $r7, $stay);
        });
        $scheduler->spawn(10, function () use ($service, $omar, $r7, $stay, &$answers): void {
            $answers[1] = (string) $service->reserve($omar, $r7, $stay);
        });
        $scheduler->run();
        ksort($answers);

        return [$store, array_values($answers)];
    }
}
