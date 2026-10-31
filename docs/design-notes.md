# P03 design notes

## Why design before code
Low-level design decides which classes exist (Guest, Room, Reservation, DateRange, ...), what information each class keeps, which methods it has, and which class calls which, before any code is typed. The overlap rule is then written once, in DateRange.overlaps(), so the website and the reception desk can never follow different rules.

## Where the methods live
- Cancel() lives in Reservation: cancelling only changes the reservation's own status (Confirmed -> Cancelled); nothing is deleted.
- HasOverlap() lives in ReservationStore: only the store can see all the reservations of a room.
- Overlaps() lives in DateRange: it needs only the two pairs of dates that DateRange already has.
- Reserve() lives in BookingService: it runs the steps in order (check dates, check overlap, save, email, answer).

## Activity traces
- Omar, R7, 13-15 November (Maya has 12-14): first diamond "check-out after check-in?" -> Yes. Second diamond "a Confirmed booking shares a night?" -> Yes (the night of the 13th). The request ends at "Unavailable - save nothing".
- Nora, R7, 14-16 November: first diamond -> Yes. Second diamond -> No (Maya leaves on the 14th, no shared night). The reservation is saved as Confirmed, the email is sent, the answer is "Accepted R102".
- Omar, R8, 15-15 November: first diamond -> No (check-out is not after check-in). The request ends at "InvalidDates - save nothing".

## Prediction
Nora asks for R7 on 14-16 November: Accepted R102, because Maya checks out on the 14th and the check-out day is free.

## The race
Prediction: R7 will have 2 Confirmed reservations for 12-14 November.
What happened: Maya and Omar both got Accepted (R101 and R102), so R7 has two Confirmed reservations for the same nights and requirement R3 is broken. The problem appears between the two calls in Reserve: HasOverlap() (check) and Save() (act). Both requests checked before either one saved, so both saw an empty room. P09 fixes it in the database.

## Laravel mapping
| Diagram class | Laravel file |
|---|---|
| DateRange | app/Hotel/Domain/DateRange.php (value object) |
| ReservationStatus | app/Hotel/Domain/ReservationStatus.php (enum) |
| Guest, Room, Reservation | app/Models/*.php (Eloquent) |
| ReservationStore | app/Hotel/Persistence/ReservationStore.php + EloquentReservationStore.php (repository, PostgreSQL) |
| EmailSender | app/Hotel/Notification/EmailSender.php + SimulatedEmailSender.php |
| BookingService, BookingResult | app/Hotel/Booking/ |
| Demo and race | php artisan hotel:demo [--race], php artisan hotel:show R101 |

# P04 design notes

## Refactoring
Refactoring changes how the code is organised, but not what the app does for the guest.
Must stay the same:
- the tests pass after every move,
- the demo prints the same nine lines as in P03,
- the race still double-books (refactoring does not fix bugs; P09 does).

## What our code breaks
- The interface was called EmailSender, but the hotel wants SMS too (O: open/closed). The name promised one way of sending; BookingService should only know "a notification". Renamed to NotificationSender.
- There was no store without a database (D: dependency inversion), so every test needed PostgreSQL and was slow. Added InMemoryReservationStore.
- A sender that throws after the reservation was saved would break Reserve and hide the saved booking from the caller. This is the new rule R6, fixed in Step 7.
- A demo pause sits inside Reserve() on purpose, for the race. It goes in P09.

## Laravel mapping (after P04)
| Diagram class | Laravel file |
|---|---|
| BookingService | app/Hotel/Booking/BookingService.php |
| IReservationStore | app/Hotel/Persistence/ReservationStore.php |
| InMemoryReservationStore | app/Hotel/Persistence/InMemoryReservationStore.php |
| EloquentReservationStore (PostgreSQL) | app/Hotel/Persistence/EloquentReservationStore.php |
| INotificationSender | app/Hotel/Notification/NotificationSender.php |
| EmailNotificationSender, SmsNotificationSender | app/Hotel/Notification/ |
| FakeNotificationSender (tests only) | tests/Support/FakeNotificationSender.php |
| Composition root | app/Providers/HotelServiceProvider.php |

## Proof
- tests: all pass (the old tests unchanged, plus 8 new tests without a database)
- demo: the same nine lines as in P03
- race: still "Double booking!"

# P06 design notes

## Before

- Tests: 24 green (8 behaviour tests in BookingServiceWithFakesTest, 16 others).
- Demo (php artisan hotel:demo): the nine lines, ending with "Confirmation emails sent: 2".
- Race (php artisan hotel:demo --race): "Double booking!" (P09 fixes it).

## Modules

| Module | Public contract (others may use) | Internal code (only the module, the wiring and the tests) |
|---|---|---|
| Rooms | Room, RoomCatalog | InMemoryRoomCatalog, EloquentRoomCatalog |
| Reservations | BookingService, BookingResult, BookingOutcome, Reservation, Guest, DateRange, ReservationStatus, ReservationStore | InMemoryReservationStore, EloquentReservationStore |
| Payments | PaymentStatus, PaymentService | SimulatedPaymentService |
| Notifications | Confirmation, NotificationSender | EmailNotificationSender, SmsNotificationSender |

The wiring is App\Providers\HotelServiceProvider. App\Hotel\Support (Pause, FiberPause,
FiberScheduler) only serves the race demo and stays outside the modules until P09.

## Architecture rules

1. Reservations uses only the public contracts of other modules: BookingService may use the Notifications contract, never the email sender.
2. Rooms does not depend on any other module.
3. Payments and Notifications do not depend on Reservations: the arrows point one way.

## Broken on purpose

Rule 1 failed when BookingService named EmailNotificationSender directly. The message
named app/Hotel/Reservations/BookingService.php and App\Hotel\Notifications\Internal\EmailNotificationSender.
The behaviour tests stayed green: only the architecture test sees a wrong dependency.

## After

- Tests: 27 green (the 24 from before and the 3 architecture checks).
- Demo: the same nine lines as in "Before".
- Race: still "Double booking!" (P09 fixes it).

# P07 design notes

## Why HTTP
The booking rules were only reachable from the command line. HTTP lets a website and the reception desk
use the same rules. The controller only translates: HTTP request -> BookingService call -> HTTP answer.
It never repeats a rule (the overlap rule stays in DateRange, the booking rule in BookingService).

## Outcome -> HTTP status

| BookingService answer | HTTP |
|---|---|
| Accepted | 201 {id, status, notificationFailed} |
| Unavailable | 409 problem Unavailable |
| InvalidDates | 400 problem InvalidDates |
| cancel true / false | 204 / 404 problem NotFound |
| unknown room, bad body, bad limit | 400 problem UnknownRoom / InvalidRequest |

## Laravel mapping (after P07)

| Part | File |
|---|---|
| The three endpoints | app/Http/Controllers/HotelApiController.php |
| Problem details | app/Http/Problem.php |
| Routes (no /api prefix) | routes/api.php, bootstrap/app.php |
| The contract | docs/openapi.yaml (also served at GET /openapi.yaml) |
| Database | docker-compose.yml (PostgreSQL 18, host port 5433); tables from the migrations |

## Rule
The HTTP API uses only public contracts of the modules (tests/Unit/HttpBorderTest.php).

# P07 design notes

## Why HTTP
The booking rules were only reachable from the command line. HTTP lets a website and the reception desk
use the same rules. The controller only translates: HTTP request -> BookingService call -> HTTP answer.
It never repeats a rule (the overlap rule stays in DateRange, the booking rule in BookingService).

## Outcome -> HTTP status

| BookingService answer | HTTP |
|---|---|
| Accepted | 201 {id, status, notificationFailed} |
| Unavailable | 409 problem Unavailable |
| InvalidDates | 400 problem InvalidDates |
| cancel true / false | 204 / 404 problem NotFound |
| unknown room, bad body, bad limit | 400 problem UnknownRoom / InvalidRequest |

## Laravel mapping (after P07)

| Part | File |
|---|---|
| The three endpoints | app/Http/Controllers/HotelApiController.php |
| Problem details | app/Http/Problem.php |
| Routes (no /api prefix) | routes/api.php, bootstrap/app.php |
| The contract | docs/openapi.yaml (also served at GET /openapi.yaml) |
| Database | docker-compose.yml (PostgreSQL 18, host port 5433); tables from the migrations |

## Rule
The HTTP API uses only public contracts of the modules (tests/Unit/HttpBorderTest.php).

## After (P07)

- Tests: 34 green (the 27 from P06, 1 HTTP border check, 6 API tests).
- Eight requests in docs/api-check.md: 200, 201, 200 (only R8), 409, 400, 400, 204, 404.
- Database for the running app: the local PostgreSQL (database hotel_booking). docker-compose.yml is
  the Docker version of the same database; Docker is not installed on this Mac, so it was not run here.
- Race: still "Double booking!" (P09 fixes it).

# P08 design notes

## Cache rule
Booking never reads the cache. Only the free-room search does; PostgreSQL decides every booking.

## What changed
- app/Api/: KeyValueStore (3 commands), LaravelKeyValueStore, AvailabilityCache, Stats, CountingReservationStore.
- GET /stats: four counters. They live in the cache store, because `php artisan serve` starts a new process per request.
- HOTEL_CACHE=false switches the search cache off, to measure the search without it.
- Redis and Docker were not available on this Mac: the file cache store was used (CACHE_STORE=file).

## After
- Tests: 41 green (the 34 from P07, 4 cache-logic tests, 3 cache API tests).
- Measurements: 2,000 database queries without the cache, 2 with it (docs/cache-experiment.md).
- Stale result seen with the four requests and fixed by the version number.
- Race: still "Double booking!" (P09 fixes it).
