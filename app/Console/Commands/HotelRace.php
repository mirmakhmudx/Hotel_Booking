<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\RaceRunner;
use App\Hotel\Reservations\ConcurrencyStrategy;
use App\Hotel\Reservations\Internal\NoDoubleBookingConstraint;
use App\Hotel\Reservations\Reservation;
use Illuminate\Console\Command;

final class HotelRace extends Command
{
    protected $signature = 'hotel:race
        {--strategy=lock : unprotected | atomic | optimistic | lock | all}
        {--pairs=100 : how many pairs of guests (S2 asks for 100)}
        {--pause=200 : milliseconds between the check and the save}
        {--without-constraint : drop the database constraint for this run, so only the strategy protects}';

    protected $description = 'P09: pairs of guests (two real processes each) book the same room at the same moment (clears all reservations)';

    public function handle(RaceRunner $runner): int
    {
        // Two races at once share one table and one constraint and spoil each other: allow only one.
        $lock = fopen(storage_path('framework/hotel-race.lock'), 'c'); // released when this process ends

        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->error('Another hotel:race is already running. Wait for it to finish before starting a new one.');

            return self::FAILURE;
        }

        $names = $this->option('strategy') === 'all'
            ? array_map(static fn (ConcurrencyStrategy $s): string => $s->value, ConcurrencyStrategy::cases())
            : [(string) $this->option('strategy')];

        $without = (bool) $this->option('without-constraint');
        $failed = false;

        if ($without) {
            NoDoubleBookingConstraint::drop();
        } elseif (! NoDoubleBookingConstraint::exists()) {
            // A normal run must run with the safety net ON. It is missing only if an earlier run was interrupted.
            $this->restoreConstraint('The database constraint was missing (an earlier run was interrupted): restored.');
        }

        try {
            $this->line('Database constraint: ' . (NoDoubleBookingConstraint::exists() ? 'ON' : 'OFF'));

            foreach ($names as $name) {
                $failed = $this->runOne(ConcurrencyStrategy::from($name), $runner) || $failed;
            }
        } finally {
            if (! NoDoubleBookingConstraint::exists()) {
                $this->restoreConstraint('Database constraint restored.'); // always leave the safety net ON
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function restoreConstraint(string $message): void
    {
        Reservation::truncate(); // left-over double bookings would block the constraint
        NoDoubleBookingConstraint::add();
        $this->line($message);
    }

    private function runOne(ConcurrencyStrategy $strategy, RaceRunner $runner): bool
    {
        $pairs = max(1, (int) $this->option('pairs'));
        $results = $runner->run($strategy, $pairs, max(0, (int) $this->option('pause')));

        $double = count(array_filter($results, static fn (array $r): bool => $r['confirmed'] > 1));
        $oneWinner = count(array_filter($results, static fn (array $r): bool => $r['confirmed'] === 1 && $r['accepted'] === 1));
        $refused = count(array_filter($results, static fn (array $r): bool => array_intersect(['RefusedByDatabase', 'DeadlockVictim'], $r['answers']) !== []));
        $other = $pairs - $double - $oneWinner;

        $this->line(sprintf(
            '%-12s pairs=%d  exactly-one-booked=%d  DOUBLE BOOKINGS=%d  other=%d  (second guest stopped by the database: %d)',
            $strategy->value, $pairs, $oneWinner, $double, $other, $refused,
        ));

        foreach ($results as $r) {
            if ($r['confirmed'] !== 1 || $r['accepted'] !== 1) {
                $this->line(sprintf('  pair %d: confirmed=%d answers=[%s]', $r['pair'], $r['confirmed'], implode(' | ', $r['answers'])));
            }
        }

        return $double > 0;
    }
}
