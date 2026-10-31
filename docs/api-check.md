# API check (P07)

Start: `docker compose up -d`, `php artisan migrate:fresh --seed`, `php artisan serve --port=5080`.
Then send the requests one by one (curl on Mac). Every answer must match.

| # | Request | Answer |
|---|---|---|
| 1 | GET /rooms/availability?checkIn=2026-11-12&checkOut=2026-11-14 | 200, rooms R7 and R8, next null |
| 2 | POST /reservations (docs/requests/maya-r7.json) | 201 {"id":"R101","status":"Confirmed","notificationFailed":false} |
| 3 | GET /rooms/availability?checkIn=2026-11-12&checkOut=2026-11-14 | 200, only R8 |
| 4 | POST /reservations (docs/requests/omar-r7.json) | 409 problem: Unavailable |
| 5 | POST /reservations (docs/requests/omar-r8.json) | 400 problem: InvalidDates (check-out is not after check-in) |
| 6 | GET /rooms/availability?checkIn=2026-11-12&checkOut=2026-11-14&limit=0 | 400 problem: InvalidRequest |
| 7 | DELETE /reservations/R101 | 204, no body |
| 8 | DELETE /reservations/R101 (again) | 404 problem: NotFound |

Every error body has the same three keys: title, status, detail (problem details, RFC 9457),
with the content type application/problem+json.

Automated proof: `php artisan test` (tests/Feature/HotelApiTest.php, tests/Unit/HttpBorderTest.php).
