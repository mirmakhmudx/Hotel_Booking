<?php

declare(strict_types=1);

/**
 * Sends 1,000 searches to the running app, one after another, and prints how long they took
 * and what they cost: the difference of GET /stats before and after.
 *
 * php tools/measure.php                         -> 12-14 Nov
 * php tools/measure.php 2026-12-31 2027-01-02   -> other dates
 */
const SEARCHES = 1000;
const NAMES = ['searches', 'databaseQueries', 'cacheHits', 'cacheMisses'];

if (! extension_loaded('curl')) {
    fwrite(STDERR, "The PHP curl extension is needed (php -m | grep curl).\n");
    exit(1);
}

[$checkIn, $checkOut] = count($argv) >= 3 ? [$argv[1], $argv[2]] : ['2026-11-12', '2026-11-14'];

// One handle: curl keeps the connection open and reuses it for every request.
$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$get = function (string $path) use ($curl): string {
    curl_setopt($curl, CURLOPT_URL, 'http://localhost:5080' . $path);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($body === false || $status !== 200) {
        fwrite(STDERR, "GET {$path} answered {$status}: " . (is_string($body) ? $body : curl_error($curl)) . "\n");
        exit(1);
    }

    return $body;
};

$before = json_decode($get('/stats'), true);
$times = [];
$started = hrtime(true);

for ($i = 0; $i < SEARCHES; $i++) {
    $start = hrtime(true);
    $get("/rooms/availability?checkIn={$checkIn}&checkOut={$checkOut}");
    $times[] = (hrtime(true) - $start) / 1_000_000;
}

$total = (hrtime(true) - $started) / 1_000_000_000;
$after = json_decode($get('/stats'), true);

sort($times);
$p95 = $times[(int) ceil(0.95 * SEARCHES) - 1]; // 950 of the 1,000 searches were this fast or faster
$difference = implode(',', array_map(fn (string $name): string => sprintf('"%s":%d', $name, $after[$name] - $before[$name]), NAMES));

echo SEARCHES . " searches for {$checkIn} to {$checkOut}\n";
printf("Average: %.1f ms\n", array_sum($times) / SEARCHES);
printf("p95: %.1f ms\n", $p95);
printf("Total: %.1f s\n", $total);
echo "GET /stats difference: {{$difference}}\n";
