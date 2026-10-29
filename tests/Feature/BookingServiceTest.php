<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Hotel\Booking\BookingService;
use App\Hotel\Domain\DateRange;
use App\Hotel\Domain\ReservationStatus;
use App\Hotel\Notification\SimulatedEmailSender;
use App\Hotel\Persistence\ReservationStore;
use App\Hotel\Support\FiberPause;
use App\Hotel\Support\FiberScheduler;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/** Truncation (not transactions) is used so that ids restart and the first reservation is R101 again. */
final class BookingServiceTest extends TestCase
{
    use DatabaseTruncation;

    private BookingService $service;

    private ReservationStore $store;

    private SimulatedEmailSender $email;

    private Room $r7;

    private Room $r8;

    private Guest $maya;

    private Guest $omar;

    private Guest $nora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->r7 = Room::create(['number' => 'R7', 'type' => 'Standard']);
        $this->r8 = Room::create(['number' => 'R8', 'type' => 'Deluxe']);
        $this->maya = Guest::create(['name' => 'Maya', 'email' => 'maya@example.com']);
        $this->omar = Guest::create(['name' => 'Omar', 'email' => 'omar@example.com']);
        $this->nora = Guest::create(['name' => 'Nora', 'email' => 'nora@example.com']);

        $this->email = new SimulatedEmailSender();
        $this->store = $this->app->make(ReservationStore::class);
        $this->service = $this->app->makeWith(BookingService::class, ['email' => $this->email]);
    }

    public function test_r1_empty_hotel_shows_all_rooms(): void
    {
        $free = $this->service->findFreeRooms($this->nov(12, 14));

        $this->assertSame(['R7', 'R8'], $free->pluck('number')->all());
    }

    public function test_r2_booking_a_free_room_is_accepted_with_an_id(): void
    {
        $result = $this->service->reserve($this->maya, $this->r7, $this->nov(12, 14));

        $this->assertTrue($result->isAccepted());
        $this->assertSame('Accepted R101', (string) $result);
    }

    public function test_r3_a_clash_is_unavailable_and_saves_nothing(): void
    {
        $this->service->reserve($this->maya, $this->r7, $this->nov(12, 14));

        $result = $this->service->reserve($this->omar, $this->r7, $this->nov(13, 15));

        $this->assertSame('Unavailable', (string) $result);
        $this->assertSame(1, Reservation::count());
    }

    public function test_r3_check_out_day_is_free_for_the_next_guest(): void
    {
        $this->service->reserve($this->maya, $this->r7, $this->nov(12, 14));

        $result = $this->service->reserve($this->nora, $this->r7, $this->nov(14, 16));

        $this->assertSame('Accepted R102', (string) $result);
    }

    public function test_r3_invalid_dates_are_refused_and_save_nothing(): void
    {
        $result = $this->service->reserve($this->omar, $this->r8, $this->nov(15, 15));

        $this->assertSame('InvalidDates', (string) $result);
        $this->assertSame(0, Reservation::count());
    }

    public function test_r3_r8_examples(): void
    {
        $this->service->reserve($this->maya, $this->r8, $this->nov(20, 22));

        $this->assertSame('Accepted R102', (string) $this->service->reserve($this->omar, $this->r8, $this->nov(22, 24)));
        $this->assertSame('Unavailable', (string) $this->service->reserve($this->nora, $this->r8, $this->nov(21, 23)));
    }

    public function test_r4_cancel_frees_the_room_and_keeps_the_history(): void
    {
        $this->service->reserve($this->maya, $this->r7, $this->nov(12, 14));

        $this->assertTrue($this->service->cancel('R101'));
        $this->assertSame(['R7', 'R8'], $this->service->findFreeRooms($this->nov(12, 14))->pluck('number')->all());
        $this->assertSame(ReservationStatus::Cancelled, $this->store->find('R101')->status);
        $this->assertSame(1, Reservation::count()); // nothing is deleted
    }

    public function test_r4_cancelling_twice_or_an_unknown_id_returns_false(): void
    {
        $this->service->reserve($this->maya, $this->r7, $this->nov(12, 14));
        $this->service->cancel('R101');

        $this->assertFalse($this->service->cancel('R101'));
        $this->assertFalse($this->service->cancel('R999'));
        $this->assertFalse($this->service->cancel('nonsense'));
    }

    public function test_r5_a_reservation_can_be_viewed_by_its_id(): void
    {
        $this->service->reserve($this->maya, $this->r7, $this->nov(12, 14));

        $reservation = $this->store->find('R101');

        $this->assertSame('Maya', $reservation->guest->name);
        $this->assertSame('R7', $reservation->room->number);
        $this->assertSame('2026-11-12', $reservation->stay()->checkIn->toDateString());
        $this->assertSame('2026-11-14', $reservation->stay()->checkOut->toDateString());
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertNull($this->store->find('R999'));
    }

    public function test_confirmation_emails_are_sent_only_for_accepted_bookings(): void
    {
        $this->service->reserve($this->maya, $this->r7, $this->nov(12, 14));
        $this->service->reserve($this->omar, $this->r7, $this->nov(13, 15));
        $this->service->reserve($this->nora, $this->r7, $this->nov(14, 16));
        $this->service->reserve($this->omar, $this->r8, $this->nov(15, 15));

        $this->assertSame(2, $this->email->sent());
    }

    /** KNOWN ISSUE of version 1: it documents the bug on purpose. P09 fixes it and flips this test. */
    public function test_race_two_simultaneous_requests_double_book_the_room(): void
    {
        $service = $this->app->makeWith(BookingService::class, [
            'email' => $this->email,
            'pause' => new FiberPause(50),
        ]);
        $stay = $this->nov(12, 14);

        $scheduler = new FiberScheduler();
        $scheduler->spawn(0, fn () => $service->reserve($this->maya, $this->r7, $stay));
        $scheduler->spawn(10, fn () => $service->reserve($this->omar, $this->r7, $stay));
        $scheduler->run();

        $this->assertSame(2, $this->store->countConfirmed($this->r7, $stay));
    }

    private function nov(int $checkIn, int $checkOut): DateRange
    {
        return new DateRange(sprintf('2026-11-%02d', $checkIn), sprintf('2026-11-%02d', $checkOut));
    }
}
