<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Hotel\Rooms\Room;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/** P07: the three endpoints and the contract, over real HTTP requests to the app (no server needed). */
final class HotelApiTest extends TestCase
{
    use DatabaseTruncation;

    private const SEARCH = '/rooms/availability?checkIn=2026-11-12&checkOut=2026-11-14';

    private const DATE_DETAIL = 'checkIn and checkOut must be dates such as 2026-11-12.';

    private const BODY_DETAIL = 'The body must be JSON with guestName, guestEmail, room, checkIn and checkOut.';

    protected function setUp(): void
    {
        parent::setUp();

        Room::create(['number' => 'R7', 'type' => 'Standard']);
        Room::create(['number' => 'R8', 'type' => 'Deluxe']);
    }

    public function test_search_lists_the_free_rooms_page_by_page(): void
    {
        $this->getJson(self::SEARCH)->assertOk()->assertExactJson([
            'checkIn' => '2026-11-12',
            'checkOut' => '2026-11-14',
            'rooms' => [['number' => 'R7', 'type' => 'Standard'], ['number' => 'R8', 'type' => 'Deluxe']],
            'next' => null,
        ]);

        $this->getJson(self::SEARCH . '&limit=1')->assertOk()->assertExactJson([
            'checkIn' => '2026-11-12',
            'checkOut' => '2026-11-14',
            'rooms' => [['number' => 'R7', 'type' => 'Standard']],
            'next' => 'R7',
        ]);

        $this->getJson(self::SEARCH . '&limit=1&after=R7')->assertOk()->assertExactJson([
            'checkIn' => '2026-11-12',
            'checkOut' => '2026-11-14',
            'rooms' => [['number' => 'R8', 'type' => 'Deluxe']],
            'next' => null,
        ]);
    }

    public function test_search_refuses_bad_input_with_problem_details(): void
    {
        $this->getJson('/rooms/availability')
            ->assertStatus(400)
            ->assertExactJson(['title' => 'InvalidDates', 'status' => 400, 'detail' => self::DATE_DETAIL]);

        $this->getJson('/rooms/availability?checkIn=12-11-2026&checkOut=2026-11-14')
            ->assertStatus(400)
            ->assertJsonPath('title', 'InvalidDates');

        $response = $this->getJson('/rooms/availability?checkIn=2026-11-15&checkOut=2026-11-15');
        $response->assertStatus(400)->assertExactJson([
            'title' => 'InvalidDates',
            'status' => 400,
            'detail' => 'checkOut must be after checkIn.',
        ]);
        $this->assertStringStartsWith('application/problem+json', (string) $response->headers->get('Content-Type'));

        foreach (['0', '101', 'abc', '-1'] as $limit) {
            $this->getJson(self::SEARCH . '&limit=' . $limit)
                ->assertStatus(400)
                ->assertJsonPath('title', 'InvalidRequest');
        }
    }

    public function test_booking_answers_201_then_409_for_a_shared_night(): void
    {
        $this->postJson('/reservations', $this->body('Maya', 'R7', '2026-11-12', '2026-11-14'))
            ->assertStatus(201)
            ->assertExactJson(['id' => 'R101', 'status' => 'Confirmed', 'notificationFailed' => false]);

        $this->postJson('/reservations', $this->body('Omar', 'R7', '2026-11-13', '2026-11-15'))
            ->assertStatus(409)
            ->assertExactJson([
                'title' => 'Unavailable',
                'status' => 409,
                'detail' => 'The room is booked for at least one night of this stay.',
            ]);

        $this->postJson('/reservations', $this->body('Nora', 'R7', '2026-11-14', '2026-11-16'))
            ->assertStatus(201)
            ->assertJsonPath('id', 'R102');

        $this->getJson(self::SEARCH)->assertOk()->assertJsonPath('rooms.0.number', 'R8')->assertJsonCount(1, 'rooms');
    }

    public function test_booking_refuses_bad_bodies_with_problem_details(): void
    {
        $this->call('POST', '/reservations', [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'hello')
            ->assertStatus(400)
            ->assertExactJson(['title' => 'InvalidRequest', 'status' => 400, 'detail' => self::BODY_DETAIL]);

        $this->postJson('/reservations', ['guestName' => 'Maya'])
            ->assertStatus(400)
            ->assertJsonPath('title', 'InvalidRequest');

        $this->postJson('/reservations', $this->body('Maya', 'R7', '2026-13-45', '2026-11-14'))
            ->assertStatus(400)
            ->assertExactJson(['title' => 'InvalidDates', 'status' => 400, 'detail' => self::DATE_DETAIL]);

        $this->postJson('/reservations', $this->body('Maya', 'R99', '2026-11-12', '2026-11-14'))
            ->assertStatus(400)
            ->assertExactJson(['title' => 'UnknownRoom', 'status' => 400, 'detail' => 'There is no room R99.']);

        $this->postJson('/reservations', $this->body('Omar', 'R8', '2026-11-15', '2026-11-15'))
            ->assertStatus(400)
            ->assertExactJson(['title' => 'InvalidDates', 'status' => 400, 'detail' => 'checkOut must be after checkIn.']);
    }

    public function test_cancel_answers_204_then_404_and_frees_the_room(): void
    {
        $this->postJson('/reservations', $this->body('Maya', 'R7', '2026-11-12', '2026-11-14'))->assertStatus(201);

        $this->deleteJson('/reservations/R101')->assertNoContent();
        $this->getJson(self::SEARCH)->assertOk()->assertJsonCount(2, 'rooms');

        $this->deleteJson('/reservations/R101')
            ->assertStatus(404)
            ->assertExactJson([
                'title' => 'NotFound',
                'status' => 404,
                'detail' => 'There is no confirmed reservation R101.',
            ]);
        $this->deleteJson('/reservations/R999')->assertStatus(404);
    }

    public function test_the_contract_is_served_by_the_app(): void
    {
        $response = $this->get('/openapi.yaml');

        $response->assertOk();
        $this->assertStringStartsWith('application/yaml', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('openapi: 3.1.0', (string) $response->getContent());
    }

    /** @return array<string, string> */
    private function body(string $name, string $room, string $checkIn, string $checkOut): array
    {
        return [
            'guestName' => $name,
            'guestEmail' => strtolower($name) . '@example.com',
            'room' => $room,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
        ];
    }
}
