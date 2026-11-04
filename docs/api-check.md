# API check (P07): the requests against PostgreSQL

Start from an empty database, then start the app in a second terminal:

```text
php artisan migrate:fresh --seed
php artisan serve --port=5080
```

Run each request from the project folder (on a Mac, `curl`). It prints the body, then the status code.
Write ✓ in the last column when your answer is exactly the expected one. The ✓ marks are yours to fill in
after you have run the requests; they are empty on purpose.

| # | Request | Expected | ✓ |
|---|---|---|---|
| 1 | `curl -s -w "\n%{http_code}\n" "http://localhost:5080/rooms/availability?checkIn=2026-11-12&checkOut=2026-11-14"` | `{"checkIn":"2026-11-12","checkOut":"2026-11-14","rooms":[{"number":"R7","type":"Standard"},{"number":"R8","type":"Deluxe"}],"next":null}` then `200` | |
| 2 | `curl -s -w "\n%{http_code}\n" -X POST -H "Content-Type: application/json" --data "@docs/requests/maya-r7.json" http://localhost:5080/reservations` | `{"id":"R101","status":"Confirmed","notificationFailed":false}` then `201` | |
| 3 | the same with `docs/requests/omar-r7.json` (13-15 Nov: the night of the 13th is Maya's) | `{"title":"Unavailable","status":409,"detail":"The room is booked for at least one night of this stay."}` then `409` | |
| 4 | the same with `docs/requests/omar-r8.json` (15-15 Nov: no night at all) | `{"title":"InvalidDates","status":400,"detail":"checkOut must be after checkIn."}` then `400` | |
| 5 | search 12-14 Nov again (as request 1) | only R8 is free: `...,"rooms":[{"number":"R8","type":"Deluxe"}],"next":null}` then `200` | |
| 6 | `curl -s -w "\n%{http_code}\n" -X DELETE http://localhost:5080/reservations/R101` | an empty line, then `204` | |
| 7 | the same DELETE again | `{"title":"NotFound","status":404,"detail":"There is no confirmed reservation R101."}` then `404` | |

Every error body has the same three keys: title, status, detail (problem details, RFC 9457),
with the content type `application/problem+json`.

Extra check from P07: `.../rooms/availability?checkIn=2026-11-12&checkOut=2026-11-14&limit=0` answers `400` with the title `InvalidRequest`.

## Restart check: the booking survives

| # | Step | Expected | ✓ |
|---|---|---|---|
| 8 | Maya books R7 for 12-14 Nov again (request 2) | `{"id":"R102","status":"Confirmed","notificationFailed":false}` then `201` | |
| 9 | Stop the app (Ctrl+C in its terminal) and start it again: `php artisan serve --port=5080` | the app listens again | |
| 10 | Search 12-14 Nov (request 1) | only R8 is free, `200` | |

Then look inside the database (use the user and database name from your `.env`; here `postgres` and `hotel_booking`):

```text
psql -U postgres -d hotel_booking -c "SELECT id, guest_id, room_id, check_in, check_out, status FROM reservations ORDER BY id;"
```

Expected: two rows, the first (R101) `Cancelled`, the second (R102) `Confirmed`. R7 is still booked after the restart:
the reservation lives in PostgreSQL, not in the app's memory.

Automated proof: `php artisan test` (tests/Feature/HotelApiTest.php, tests/Unit/HttpBorderTest.php).
