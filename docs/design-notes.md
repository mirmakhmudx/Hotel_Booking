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
