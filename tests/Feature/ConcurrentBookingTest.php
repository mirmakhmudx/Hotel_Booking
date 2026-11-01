<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\RaceRunner;
use App\Hotel\Reservations\ConcurrencyStrategy;
use App\Hotel\Reservations\Internal\NoDoubleBookingConstraint;
use App\Hotel\Reservations\Reservation;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * REAL concurrency: each pair is two separate PHP processes with two PostgreSQL connections, released
 * at the same moment, with a 100 ms gap between the check and the save. Needs PostgreSQL (see phpunit.xml).
 * (Fibers share one connection and cannot test locks.)
 */
final class ConcurrentBookingTest extends TestCase
{
    use DatabaseTruncation;

    private const PAIRS = 5;

    private const PAUSE_MS = 100;

    /** Without the constraint, only the strategy stands between the two guests. */
    #[DataProvider('strategiesThatMustProtectOnTheirOwn')]
    public function test_the_strategy_alone_never_double_books(ConcurrencyStrategy $strategy): void
    {
        $results = $this->raceWithoutConstraint($strategy);

        foreach ($results as $pair) {
            $this->assertSame(1, $pair['confirmed'], "pair {$pair['pair']}: " . implode(' | ', $pair['answers']));
            $this->assertSame(1, $pair['accepted'], "pair {$pair['pair']}: " . implode(' | ', $pair['answers']));
            $this->assertContains('Unavailable', $pair['answers'], "the loser must hear Unavailable, pair {$pair['pair']}");
        }
    }

    public static function strategiesThatMustProtectOnTheirOwn(): array
    {
        return [
            'optimistic version check' => [ConcurrencyStrategy::Optimistic],
            'short row-lock transaction' => [ConcurrencyStrategy::RowLock],
        ];
    }

    /** The atomic insert is only complete together with the constraint: that is the realistic set-up. */
    public function test_atomic_insert_with_the_constraint_never_double_books(): void
    {
        $this->assertTrue(NoDoubleBookingConstraint::exists());

        foreach ($this->race(ConcurrencyStrategy::AtomicInsert) as $pair) {
            $this->assertSame(1, $pair['confirmed'], "pair {$pair['pair']}: " . implode(' | ', $pair['answers']));
            $this->assertSame(1, $pair['accepted'], "pair {$pair['pair']}: " . implode(' | ', $pair['answers']));
        }
    }

    /** The same race on the version 1 code, no constraint: the double booking really happens. */
    public function test_without_protection_the_same_race_double_books(): void
    {
        $results = $this->raceWithoutConstraint(ConcurrencyStrategy::Unprotected);

        $doubles = count(array_filter($results, static fn (array $r): bool => $r['confirmed'] > 1));

        $this->assertGreaterThan(0, $doubles, 'The race should double-book at least once in ' . self::PAIRS . ' pairs.');
    }

    /** With the constraint, even the version 1 code cannot leave two bookings: the database stops the second. */
    public function test_the_constraint_alone_stops_the_unprotected_code(): void
    {
        foreach ($this->race(ConcurrencyStrategy::Unprotected) as $pair) {
            $this->assertSame(1, $pair['confirmed'], "pair {$pair['pair']}: " . implode(' | ', $pair['answers']));
            // the database stopped the second guest: either it refused the row (23P01) or aborted it as a deadlock victim (40P01)
            $this->assertNotEmpty(
                array_intersect(['RefusedByDatabase', 'DeadlockVictim'], $pair['answers']),
                "pair {$pair['pair']}: " . implode(' | ', $pair['answers']),
            );
        }
    }

    /** @return list<array{pair: int, answers: list<string>, accepted: int, confirmed: int}> */
    private function raceWithoutConstraint(ConcurrencyStrategy $strategy): array
    {
        NoDoubleBookingConstraint::drop();

        try {
            return $this->race($strategy);
        } finally {
            Reservation::truncate(); // the race left double bookings: the constraint cannot be added while they exist
            NoDoubleBookingConstraint::add();
        }
    }

    /** @return list<array{pair: int, answers: list<string>, accepted: int, confirmed: int}> */
    private function race(ConcurrencyStrategy $strategy): array
    {
        return (new RaceRunner())->run($strategy, self::PAIRS, self::PAUSE_MS, [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ]);
    }
}
