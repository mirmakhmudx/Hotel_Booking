<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Hotel\Reservations\BookingOutcome;
use App\Hotel\Reservations\BookingResult;
use App\Hotel\Reservations\BookingService;
use App\Hotel\Reservations\DateRange;
use App\Hotel\Reservations\Guest;
use App\Hotel\Rooms\Room;
use App\Hotel\Rooms\RoomCatalog;
use App\Http\Problem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The HTTP API: three endpoints that turn HTTP requests into BookingService calls and back.
 * It lives outside the four modules and uses only their public contracts
 * (HotelServiceProvider decides which classes stand behind them).
 */
final class HotelApiController extends Controller
{
    private const FIELDS = ['guestName', 'guestEmail', 'room', 'checkIn', 'checkOut'];

    private const BODY_DETAIL = 'The body must be JSON with guestName, guestEmail, room, checkIn and checkOut.';

    private const DATE_DETAIL = 'checkIn and checkOut must be dates such as 2026-11-12.';

    private const STAY_DETAIL = 'checkOut must be after checkIn.';

    public function __construct(
        private readonly BookingService $booking,
        private readonly RoomCatalog $rooms,
    ) {
    }

    /** GET /rooms/availability?checkIn=...&checkOut=...&limit=20&after=R7 (R1) */
    public function availability(Request $request): JsonResponse
    {
        $checkIn = self::parseDate($request->query('checkIn'));
        $checkOut = self::parseDate($request->query('checkOut'));

        if ($checkIn === null || $checkOut === null) {
            return Problem::response(400, 'InvalidDates', self::DATE_DETAIL);
        }

        $stay = new DateRange($checkIn, $checkOut);

        if (! $stay->isValid()) {
            return Problem::response(400, 'InvalidDates', self::STAY_DETAIL);
        }

        $limit = $request->query('limit', '20');

        if (! is_string($limit) || preg_match('/^[0-9]{1,3}$/D', $limit) !== 1 || (int) $limit < 1 || (int) $limit > 100) {
            return Problem::response(400, 'InvalidRequest', 'limit must be a whole number from 1 to 100.');
        }

        $after = $request->query('after', '');
        $after = is_string($after) ? $after : '';

        // Cursor pagination (L04): the rooms after the last one the client has seen, in text order.
        $rest = $this->booking->findFreeRooms($stay)
            ->filter(fn (Room $room): bool => strcmp((string) $room->number, $after) > 0)
            ->sortBy(fn (Room $room): string => (string) $room->number, SORT_STRING)
            ->values();
        $page = $rest->take((int) $limit)->values();

        return response()->json([
            'checkIn' => $request->query('checkIn'),
            'checkOut' => $request->query('checkOut'),
            'rooms' => $page->map(fn (Room $room): array => ['number' => $room->number, 'type' => $room->type])->all(),
            'next' => $rest->count() > $page->count() ? $page->last()->number : null,
        ]);
    }

    /** POST /reservations (R2, R3, R6) */
    public function reserve(Request $request): JsonResponse
    {
        $body = $request->isJson() ? $request->json()->all() : [];

        foreach (self::FIELDS as $field) {
            if (! is_string($body[$field] ?? null) || $body[$field] === '') {
                return Problem::response(400, 'InvalidRequest', self::BODY_DETAIL);
            }
        }

        $checkIn = self::parseDate($body['checkIn']);
        $checkOut = self::parseDate($body['checkOut']);

        if ($checkIn === null || $checkOut === null) {
            return Problem::response(400, 'InvalidDates', self::DATE_DETAIL);
        }

        $room = $this->rooms->all()->first(fn (Room $candidate): bool => $candidate->number === $body['room']);

        if ($room === null) {
            return Problem::response(400, 'UnknownRoom', 'There is no room ' . $body['room'] . '.');
        }

        $guest = Guest::query()->firstOrCreate(
            ['email' => $body['guestEmail']],
            ['name' => $body['guestName']],
        );

        return $this->toHttp($this->booking->reserve($guest, $room, new DateRange($checkIn, $checkOut)));
    }

    /** DELETE /reservations/{id} (R4) */
    public function cancel(string $id): JsonResponse|Response
    {
        if (! $this->booking->cancel($id)) {
            return Problem::response(404, 'NotFound', 'There is no confirmed reservation ' . $id . '.');
        }

        return response()->noContent();
    }

    /** Turns a booking answer into an HTTP status code and a JSON body (L04). */
    private function toHttp(BookingResult $result): JsonResponse
    {
        return match ($result->outcome) {
            BookingOutcome::Accepted => response()->json([
                'id' => $result->reservationCode,
                'status' => 'Confirmed',
                'notificationFailed' => $result->notificationFailed,
            ], 201),
            BookingOutcome::Unavailable => Problem::response(409, 'Unavailable', 'The room is booked for at least one night of this stay.'),
            BookingOutcome::InvalidDates => Problem::response(400, 'InvalidDates', self::STAY_DETAIL),
        };
    }

    /** A date written exactly like 2026-11-12, otherwise null. */
    private static function parseDate(mixed $text): ?string
    {
        if (! is_string($text) || preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $text, $parts) !== 1) {
            return null;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $text : null;
    }
}
