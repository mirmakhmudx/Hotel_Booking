# Requirements: hotel booking app, version 1

Date rule: a stay uses the nights from check-in up to, but not including, the check-out day (half-open range).

## R1 - Search
A guest or receptionist can list the rooms that are free for given dates.
Check: empty hotel, 12-14 November -> R7, R8.
Proof: `Free rooms 12-14 Nov: R7, R8`

## R2 - Book
A guest or receptionist can book a free room. The app answers with a reservation id.
Check: Maya books R7 for 12-14 November -> Accepted R101.
Proof: `Maya books R7 12-14 Nov: Accepted R101`

## R3 - No clash
A room never has two Confirmed bookings on the same night. Check-out must be after check-in.
Check: Omar asks for R7 on 13-15 -> Unavailable. 15-15 -> InvalidDates.

More examples:
- Maya R7 12-14 (already booked) -> Confirmed
- Omar R7 13-15 -> Unavailable (night of the 13th)
- Nora R7 14-16 -> Accepted (no shared night, the 14th is the check-out day)
- R8 is already booked for 20-22 November. A request for R8 22-24 -> Accepted (no shared night)
- R8 is already booked for 20-22 November. A request for R8 21-23 -> Unavailable (shared night: the 21st)

Proof:
- `Omar books R7 13-15 Nov: Unavailable`
- `Nora books R7 14-16 Nov: Accepted R102`
- `Omar books R8 15-15 Nov: InvalidDates`

Version 1 had a race: two requests at the same moment could both pass the check. P09 fixes it in the database (see docs/p09-protect-the-last-seat.md).

## R4 - Cancel
A booking can be cancelled. It is kept as Cancelled and no longer blocks the room.
Check: Maya cancels R101 -> R7 is free again for 12-14.
Proof:
- `Maya cancels R101: True`
- `Free rooms 12-14 Nov: R7, R8`

## R5 - View
A booking can be looked up by its id.
Check: R101 -> Maya, R7, 12-14 November, Confirmed.
Proof: `php artisan hotel:show R101` (after `php artisan hotel:demo --race`) prints `R101: Maya, R7, 12 November - 14 November, Confirmed`

## R6 - Failed message
If the confirmation cannot be sent, the booking stays saved and Accepted, and the failure is reported.
Check: the sender fails on purpose -> `Accepted R101`, the result has notificationFailed = true, the store still holds 1 reservation.
Proof: test `test_a_failed_confirmation_keeps_the_booking` (tests/Unit/BookingServiceWithFakesTest.php)

## Automated proof
`php artisan test` -> all tests pass (R1-R6, the R3 examples, the SMS sender, the race).
