<?php

declare(strict_types=1);

namespace App\Console;

use App\Hotel\Reservations\ConcurrencyStrategy;
use App\Hotel\Reservations\Guest;
use App\Hotel\Reservations\Reservation;
use App\Hotel\Rooms\Room;
use RuntimeException;

/**
 * The real race: for each pair, two SEPARATE PHP processes (two database connections) ask for the same
 * room and the same nights. Both boot first and print READY; the parent then releases both at once.
 * Fibers cannot do this: they share one connection, so they cannot test locks.
 */
final class RaceRunner
{
    /**
     * @param array<string, string> $env extra environment for the child processes (tests set APP_ENV here)
     * @return list<array{pair: int, answers: list<string>, accepted: int, confirmed: int}>
     */
    public function run(ConcurrencyStrategy $strategy, int $pairs, int $pauseMs, array $env = []): array
    {
        Reservation::truncate(); // fresh start: every pair is independent and ids begin again

        $room = Room::query()->firstOrCreate(['number' => 'R7'], ['type' => 'Standard']);
        $guests = [
            Guest::query()->firstOrCreate(['email' => 'maya@example.com'], ['name' => 'Maya']),
            Guest::query()->firstOrCreate(['email' => 'omar@example.com'], ['name' => 'Omar']),
        ];

        $results = [];

        for ($pair = 0; $pair < $pairs; $pair++) {
            // Pair i uses its own two nights, so pairs never disturb each other.
            $checkIn = date('Y-m-d', strtotime('2027-01-01 +' . ($pair * 3) . ' days'));
            $checkOut = date('Y-m-d', strtotime($checkIn . ' +2 days'));

            $answers = $this->onePair($guests[0]->name, $guests[1]->name, $room->number, $checkIn, $checkOut, $strategy, $pauseMs, $env);

            $results[] = [
                'pair' => $pair + 1,
                'answers' => $answers,
                'accepted' => count(array_filter($answers, static fn (string $a): bool => str_starts_with($a, 'Accepted'))),
                'confirmed' => Reservation::query()
                    ->where('room_id', $room->getKey())
                    ->where('status', 'Confirmed')
                    ->whereDate('check_in', $checkIn)
                    ->count(),
            ];
        }

        return $results;
    }

    /** @return list<string> the two answers, for example ["Accepted R101", "Unavailable"] */
    private function onePair(string $first, string $second, string $room, string $in, string $out, ConcurrencyStrategy $strategy, int $pauseMs, array $env): array
    {
        $children = [];

        foreach ([$first, $second] as $guest) {
            $children[] = $this->start([PHP_BINARY, base_path('artisan'), 'hotel:book-one', $guest, $room, $in, $out, $strategy->value, (string) $pauseMs], $env);
        }

        foreach ($children as $child) {
            $line = fgets($child['pipes'][1]);

            if ($line === false || trim($line) !== 'READY') {
                throw new RuntimeException('A booking process did not become ready: ' . stream_get_contents($child['pipes'][2]));
            }
        }

        foreach ($children as $child) { // the starting gun: both are released one after the other, within microseconds
            fwrite($child['pipes'][0], "go\n");
            fclose($child['pipes'][0]);
        }

        $answers = [];

        foreach ($children as $child) {
            $output = (string) stream_get_contents($child['pipes'][1]);
            $errors = (string) stream_get_contents($child['pipes'][2]);
            fclose($child['pipes'][1]);
            fclose($child['pipes'][2]);
            proc_close($child['process']);

            $answers[] = preg_match('/^RESULT (.+)$/m', $output, $match) === 1
                ? trim($match[1])
                : 'Error: ' . trim($errors !== '' ? $errors : $output);
        }

        return $answers;
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $env
     * @return array{process: resource, pipes: array<int, resource>}
     */
    private function start(array $command, array $env): array
    {
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            array_merge(getenv(), $env),
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start a PHP process.');
        }

        return ['process' => $process, 'pipes' => $pipes];
    }
}
