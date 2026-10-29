<?php

declare(strict_types=1);

namespace App\Hotel\Reservations;

use App\Hotel\Rooms\Room;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reservation knows its own status and dates.
 * Cancelling changes the status; nothing is deleted.
 */
class Reservation extends Model
{
    private const CODE_PREFIX = 'R';

    private const FIRST_NUMBER = 101;

    protected $fillable = ['guest_id', 'room_id', 'check_in', 'check_out', 'status'];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'status' => ReservationStatus::class,
        ];
    }

    /** Builds a new Confirmed reservation. It is not saved yet. */
    public static function place(Guest $guest, Room $room, DateRange $stay): self
    {
        return new self([
            'guest_id' => $guest->getKey(),
            'room_id' => $room->getKey(),
            'check_in' => $stay->checkIn->toDateString(),
            'check_out' => $stay->checkOut->toDateString(),
            'status' => ReservationStatus::Confirmed,
        ]);
    }

    /** "R101" -> 1, "R102" -> 2. Returns null when the text is not a reservation code. */
    public static function idFromCode(string $code): ?int
    {
        if (preg_match('/^' . self::CODE_PREFIX . '(\d+)$/i', trim($code), $matches) !== 1) {
            return null;
        }

        $id = (int) $matches[1] - (self::FIRST_NUMBER - 1);

        return $id >= 1 ? $id : null;
    }

    /** The public id: R101 for the first reservation, R102 for the second, and so on. */
    protected function code(): Attribute
    {
        return Attribute::get(
            fn (): string => self::CODE_PREFIX . (self::FIRST_NUMBER - 1 + (int) $this->getKey())
        );
    }

    public function stay(): DateRange
    {
        return new DateRange($this->check_in, $this->check_out);
    }

    /** Blocks a request when it is still Confirmed for the same room and shares a night. */
    public function blocks(Room $room, DateRange $stay): bool
    {
        return $this->status === ReservationStatus::Confirmed
            && (int) $this->room_id === (int) $room->getKey()
            && $this->stay()->overlaps($stay);
    }

    /** Changes the status in memory. The caller saves it. Returns false if already cancelled. */
    public function cancel(): bool
    {
        if ($this->status === ReservationStatus::Cancelled) {
            return false;
        }

        $this->status = ReservationStatus::Cancelled;

        return true;
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
