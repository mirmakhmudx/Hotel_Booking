# Quality scenarios

## Stakeholders

| Who | What they care about |
|---|---|
| Guest | Finds a free room quickly; never loses a booking |
| Receptionist | Never promises one room twice; can cancel at the desk |
| Hotel manager (domain expert) | Knows the rules; wants deposits and SMS soon |
| Development team | Can change one area without breaking the others |
| Email/SMS and payment providers | Outside systems that can be slow or down |

## S1 · Performance: free-room search stays fast

| Part | S1 |
|---|---|
| Source | 200 guests |
| Stimulus | They search free rooms for 12–14 Nov |
| Environment | Within one minute |
| Artifact | The free-room search in BookingService (findFreeRooms) |
| Response | Every search returns the correct free rooms |
| Measure | p95 response ≤ 1 second |
| Checked in | P08 (cache), P23 (load test) |

## S2 · Data integrity: no double bookings

| Part | S2 |
|---|---|
| Source | 100 pairs of guests |
| Stimulus | Each pair books the same room and nights |
| Environment | At the same moment |
| Artifact | BookingService::reserve and the reservation store |
| Response | Exactly one Confirmed reservation per pair |
| Measure | 0 double bookings out of 100 pairs |
| Checked in | P09 |

## S3 · Availability: a failed message keeps the booking

| Part | S3 |
|---|---|
| Source | The email provider |
| Stimulus | It stops answering |
| Environment | For 10 minutes, during normal bookings |
| Artifact | BookingService::reserve and the notification sender |
| Response | Every valid booking is still Accepted and saved; the failed message is reported |
| Measure | 0 lost bookings; every failed message is counted |
| Checked in | P04 (R6 test, done) and P10 |

## S4 · Security: nobody reads another guest's reservation

| Part | S4 |
|---|---|
| Source | A guest |
| Stimulus | Tries to read another guest's reservation |
| Environment | 100 attempts, normal operation |
| Artifact | Looking up a reservation by its id (R5) |
| Response | The app refuses every attempt |
| Measure | 0 reservations shown to the wrong guest out of 100 attempts |
| Checked in | P21 |

## S5 · Modifiability: SMS without editing BookingService

| Part | S5 |
|---|---|
| Source | The hotel (the manager) |
| Stimulus | Asks for SMS confirmations |
| Environment | During development of the existing app |
| Artifact | BookingService and the notification senders |
| Response | A new sender class is added; BookingService is not edited |
| Measure | 0 edits in BookingService; at most 2 files change |
| Checked in | P04 (SmsNotificationSender, done) |

## S6 · Sustainability: less energy per search

| Part | S6 |
|---|---|
| Source | The development team |
| Stimulus | Makes a change (for example, adds a cache) |
| Environment | The same 1,000 searches, before and after the change |
| Artifact | The free-room search |
| Response | The same useful work uses less energy |
| Measure | At least 20% fewer watt-hours per 1,000 completed searches |
| Checked in | P08, P24 |