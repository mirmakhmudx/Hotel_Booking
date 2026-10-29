<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Hotel\Reservations\BookingService;
use App\Hotel\Reservations\DateRange;
use App\Hotel\Notifications\NotificationSender;
use App\Hotel\Notifications\Internal\SmsNotificationSender;
use App\Hotel\Reservations\Internal\InMemoryReservationStore;
use App\Hotel\Rooms\Internal\InMemoryRoomCatalog;
use App\Hotel\Support\NullPause;
use App\Hotel\Reservations\Guest;
use App\Hotel\Rooms\Room;
use Tests\Support\FakeNotificationSender;
use Tests\TestCase;

/** The P04 tests: no database, everything is plugged in through interfaces. */
final class BookingServiceWithFakesTest extends TestCase
{
    private InMemoryReservationStore $store;

    private Room $r7;

    private Room $r8;

    private Guest $maya;

    private Guest $omar;

    private Guest $nora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new InMemoryReservationStore();
        $this->r7 = (new Room(['number' => 'R7', 'type' => 'Standard']))->forceFill(['id' => 7]);
        $this->r8 = (new Room(['number' => 'R8', 'type' => 'Deluxe']))->forceFill(['id' => 8]);
        $this->maya = (new Guest(['name' => 'Maya', 'email' => 'maya@example.com']))->forceFill(['id' => 1]);
        $this->omar = (new Guest(['name' => 'Omar', 'email' => 'omar@example.com']))->forceFill(['id' => 2]);
        $this->nora = (new Guest(['name' => 'Nora', 'email' => 'nora@example.com']))->forceFill(['id' => 3]);
    }

    public function test_maya_gets_r7_when_it_is_free(): void
    {
        $service = $this->service(new FakeNotificationSender());

        $this->assertSame('Accepted R101', (string) $service->reserve($this->maya, $this->r7, $this->nov(12, 14)));
    }

    public function test_omar_is_refused_when_a_night_is_shared(): void
    {
        $service = $this->service(new FakeNotificationSender());
        $service->reserve($this->maya, $this->r7, $this->nov(12, 14));

        $this->assertSame('Unavailable', (string) $service->reserve($this->omar, $this->r7, $this->nov(13, 15)));
        $this->assertSame(1, $this->store->count());
    }

    public function test_nora_can_arrive_on_the_day_maya_leaves(): void
    {
        $service = $this->service(new FakeNotificationSender());
        $service->reserve($this->maya, $this->r7, $this->nov(12, 14));

        $this->assertSame('Accepted R102', (string) $service->reserve($this->nora, $this->r7, $this->nov(14, 16)));
    }

    public function test_check_out_must_be_after_check_in(): void
    {
        $service = $this->service(new FakeNotificationSender());

        $this->assertSame('InvalidDates', (string) $service->reserve($this->omar, $this->r8, $this->nov(15, 15)));
        $this->assertSame(0, $this->store->count());
    }

    public function test_cancelling_frees_the_room_again(): void
    {
        $service = $this->service(new FakeNotificationSender());
        $service->reserve($this->maya, $this->r7, $this->nov(12, 14));

        $this->assertTrue($service->cancel('R101'));
        $this->assertContains('R7', $service->findFreeRooms($this->nov(12, 14))->pluck('number')->all());
    }

    public function test_one_confirmation_per_accepted_booking(): void
    {
        $sender = new FakeNotificationSender();
        $service = $this->service($sender);

        $service->reserve($this->maya, $this->r7, $this->nov(12, 14));
        $service->reserve($this->omar, $this->r7, $this->nov(13, 15));
        $service->reserve($this->nora, $this->r7, $this->nov(14, 16));

        $this->assertSame(['R101', 'R102'], $sender->sent);
    }

    public function test_a_failed_confirmation_keeps_the_booking(): void
    {
        $service = $this->service(new FakeNotificationSender(fail: true));

        $result = $service->reserve($this->maya, $this->r7, $this->nov(12, 14));

        $this->assertSame('Accepted R101', (string) $result);
        $this->assertTrue($result->notificationFailed);
        $this->assertSame(1, $this->store->count());
    }

    public function test_sms_confirmations_need_no_change_to_booking_service(): void
    {
        $sms = new SmsNotificationSender();
        $service = $this->service($sms);

        $service->reserve($this->maya, $this->r7, $this->nov(12, 14));

        $this->assertSame(1, $sms->sent());
    }

    private function service(NotificationSender $sender): BookingService
    {
        return new BookingService(
            new InMemoryRoomCatalog([$this->r7, $this->r8]),
            $this->store,
            $sender,
            new NullPause(),
        );
    }

    private function nov(int $checkIn, int $checkOut): DateRange
    {
        return new DateRange(sprintf('2026-11-%02d', $checkIn), sprintf('2026-11-%02d', $checkOut));
    }
}
