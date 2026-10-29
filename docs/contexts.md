# Hotel contexts

## Words

| Word | Meaning here | Name in our code |
|---|---|---|
| Guest | A person who books a room, with a name and an email | App\Models\Guest (name, email) |
| Room | A room the hotel sells, such as R7 or R8 | App\Models\Room (number, type) |
| Room type | The kind of room: Standard or Deluxe | Room.type |
| Nightly price | What one night in a room costs; Deluxe costs more than Standard | not in code yet |
| Out of service | A room that is closed for repairs and not offered to guests | not in code yet |
| Stay | Check-in to check-out, half-open: 12–14 Nov = the nights of the 12th and 13th | DateRange (checkIn, checkOut) |
| Reservation | Guest + room + stay + status, with an id such as R101 | App\Models\Reservation (code, guest, room, stay, status) |
| Confirmed | The room is promised to this guest for these nights | ReservationStatus::Confirmed |
| Cancelled | The promise is withdrawn; the nights are free again | ReservationStatus::Cancelled |
| Book | A guest or receptionist asks for a room for some nights | BookingService::reserve($guest, $room, $stay) |
| Cancel | Withdraw a reservation; it is kept as history | BookingService::cancel($reservationCode) |
| Free rooms | The rooms that no Confirmed reservation blocks for a stay | BookingService::findFreeRooms($stay) |
| Deposit | 30% of the stay's price, taken when the guest books | not in code yet |
| Paid | The deposit was received | not in code yet |
| Declined | The deposit was refused | not in code yet |
| Confirmation message | The message that tells the guest "R101 is confirmed" | NotificationSender::sendConfirmation($reservation) |
| Sent | The message arrived | not in code yet |
| Failed | The message could not be delivered | not in code yet |

## Rooms

Job: knows which rooms the hotel sells.

| Word | Meaning here |
|---|---|
| Room | A room the hotel sells, such as R7 or R8, with its type and nightly price |
| Room type | The kind of room: Standard or Deluxe |
| Nightly price | What one night in a room costs; Deluxe costs more than Standard |
| Out of service | A room that is closed for repairs and not offered to guests |

Rules this context owns:
- A room that is out of service is not offered to guests.
- The nightly price depends on the room type (Deluxe costs more than Standard).

Needs from other contexts: nothing.
Tells other contexts: the list of rooms, with number and type (answer to Reservations).

## Reservations

Job: decides whether a guest gets a room for some nights, and keeps every reservation.

| Word | Meaning here |
|---|---|
| Guest | A person who books a room, with a name and an email |
| Stay | Check-in to check-out, half-open: 12–14 Nov = the nights of the 12th and 13th |
| Reservation | Guest + room number + stay + status, with an id such as R101 |
| Confirmed | The room is promised to this guest for these nights |
| Cancelled | The promise is withdrawn; the nights are free again |
| Book | A guest or receptionist asks for a room for some nights |
| Cancel | Withdraw a reservation; it is kept as history |
| Free rooms | The rooms that no Confirmed reservation blocks for a stay |

Rules this context owns:
- A room never has two Confirmed reservations that share a night.
- Check-out must be after check-in.
- Cancelling a reservation frees its nights for new bookings.

Needs from other contexts: the list of rooms (Rooms); from P17, a deposit taken (Payments).
Tells other contexts: ReservationConfirmed (Notifications).

## Payments

Job: takes deposits and records whether they were paid.

| Word | Meaning here |
|---|---|
| Deposit | 30% of the stay's price, taken when the guest books |
| Paid | The deposit was received |
| Declined | The deposit was refused |

Rules this context owns:
- The deposit is 30% of the stay's price.
- Only a Paid deposit can be refunded.

Needs from other contexts: the reservation id and the amount to take (from Reservations, P17); the nightly price (from Rooms).
Tells other contexts: Paid or Declined (answer to Reservations).

## Notifications

Job: tells guests what happened, by email or SMS.

| Word | Meaning here |
|---|---|
| Confirmation message | The message that tells the guest "R101 is confirmed" |
| Sent | The message arrived |
| Failed | The message could not be delivered |

Rules this context owns:
- A failed message is recorded as Failed and never changes the reservation.

Needs from other contexts: ReservationConfirmed, with its five fields (from Reservations).
Tells other contexts: nothing.

## Same word, different meaning

| Word | Rooms | Reservations | Payments | Notifications |
|---|---|---|---|---|
| Room | R8: Deluxe, nightly price, out of service | Just the number R8 on a reservation | Not used | Text in the message: "Room R8" |
| Status | In service / out of service | Confirmed / Cancelled | Paid / Declined | Sent / Failed |
| Cancel | Take a room off sale | Withdraw a reservation | Refund a deposit | Not used |

If Notifications could change a reservation's status to Failed, a failed SMS or email could overwrite Confirmed, and Maya would lose R7 only because of a message; this breaks rule R6 (a failed message never changes the booking).

## Rule owners

| Card | Rule | Owner | Why this owner |
|---|---|---|---|
| 1 | A room never has two Confirmed reservations that share a night. | Reservations | It keeps every reservation of the room, so it can check them all together. |
| 2 | Check-out must be after check-in. | Reservations | The stay is its word, and it refuses wrong dates before anything else. |
| 3 | Cancelling a reservation frees its nights for new bookings. | Reservations | Only Confirmed reservations block; a Cancelled one no longer does. |
| 4 | A room that is out of service is not offered to guests. | Rooms | It knows which rooms are on sale. |
| 5 | The nightly price depends on the room type (Deluxe costs more than Standard). | Rooms | It keeps each room's type and price. |
| 6 | The deposit is 30% of the stay's price. | Payments | Money rules belong to Payments; it asks Rooms for the nightly price. |
| 7 | Only a Paid deposit can be refunded. | Payments | It keeps the payment status. |
| 8 | A failed message is recorded as Failed and never changes the reservation. | Notifications | It keeps the message status (rule R6). |

## Between contexts

### Reservations → Rooms: which rooms exist? (question)

Sent when: a guest searches free rooms, or a booking needs the room.

What Reservations sends: the question "which rooms exist?" with no other data.

The answer and its fields:
- roomNumber: R7
- roomType: Standard
(and R8 Deluxe; one entry per room)

Never crosses: Reservations' list of reservations. Rooms never asks about reservations.

### Reservations → Payments: take a deposit (question, from P17)

Sent when: a booking is made and a deposit must be taken (from P17).

What Reservations sends:
- reservationId: R101
- amount: 30% of the stay's price

The answer and its fields:
- status: Paid or Declined

Never crosses: the stay's dates and the guest's other reservations. Payments does not decide whether the room is free.

### Reservations → Notifications: ReservationConfirmed (event)

Sent when: a reservation is saved as Confirmed.

Fields:
- reservationId: R101
- guestEmail: maya@example.com
- roomNumber: R7
- checkIn: 2026-11-12
- checkOut: 2026-11-14

Reservations does not wait for an answer. A failed message never changes R101 (rule R6).

Never crosses: Reservations' list of reservations. Notifications never reads it.