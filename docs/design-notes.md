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
