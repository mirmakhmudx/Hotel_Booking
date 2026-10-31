<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Api\AvailabilityCache;
use App\Hotel\Rooms\Room;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/** P08: the search is answered by the cache, a change makes it fresh, and booking never reads the cache. */
final class AvailabilityCacheApiTest extends TestCase
{
    use DatabaseTruncation;

    private const SEARCH = '/rooms/availability?checkIn=2026-11-12&checkOut=2026-11-14';

    protected function setUp(): void
    {
        parent::setUp();

        Room::create(['number' => 'R7', 'type' => 'Standard']);
        Room::create(['number' => 'R8', 'type' => 'Deluxe']);
    }

    public function test_the_second_search_is_answered_by_the_cache(): void
    {
        $first = $this->getJson(self::SEARCH)->assertOk()->getContent();
        $second = $this->getJson(self::SEARCH)->assertOk()->getContent();

        $this->assertSame($first, $second);
        $this->getJson('/stats')->assertOk()->assertExactJson([
            'searches' => 2,
            'databaseQueries' => 2,
            'cacheHits' => 1,
            'cacheMisses' => 1,
        ]);
    }

    public function test_a_booking_and_a_cancel_make_the_next_search_fresh(): void
    {
        $this->getJson(self::SEARCH)->assertJsonCount(2, 'rooms');

        $this->postJson('/reservations', $this->body('Maya', '2026-11-12', '2026-11-14'))->assertStatus(201);
        $this->getJson(self::SEARCH)->assertJsonCount(1, 'rooms')->assertJsonPath('rooms.0.number', 'R8');

        $this->deleteJson('/reservations/R101')->assertNoContent();
        $this->getJson(self::SEARCH)->assertJsonCount(2, 'rooms');
    }

    public function test_booking_never_reads_the_cache(): void
    {
        $this->postJson('/reservations', $this->body('Maya', '2026-11-12', '2026-11-14'))->assertStatus(201);

        // A wrong copy: it says R7 is free, although Maya has it.
        $cache = $this->app->make(AvailabilityCache::class);
        $cache->set($cache->key('2026-11-12', '2026-11-14'), 'R7,R8');

        $this->getJson(self::SEARCH)->assertJsonCount(2, 'rooms'); // the search shows the old copy
        $this->postJson('/reservations', $this->body('Omar', '2026-11-13', '2026-11-15'))
            ->assertStatus(409)
            ->assertJsonPath('title', 'Unavailable'); // the booking asks PostgreSQL
    }

    /** @return array<string, string> */
    private function body(string $name, string $checkIn, string $checkOut): array
    {
        return [
            'guestName' => $name,
            'guestEmail' => strtolower($name) . '@example.com',
            'room' => 'R7',
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
        ];
    }
}
