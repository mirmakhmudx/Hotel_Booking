# Capacity estimate: hotel booking app

These are estimates from assumptions, not measurements. P08 measures the real app.

## Assumptions

| Assumption | Value | Reason |
|---|---|---|
| Visitors per day | 1,440 | the booking page today: about one visitor a minute |
| Searches per visitor | 20 | guests try several dates |
| Bookings per day | 20 | 40 rooms, most stays are two nights |
| Peak factor | 10 | Friday evenings and holiday sales |
| Queries per search | 40 | FindFreeRooms asks once per room |

## Results

| Number | Calculation | Result |
|---|---|---|
| Searches, average | 1,440 × 20 = 28,800 a day; 28,800 ÷ 86,400 s | 0.33 per second |
| Searches, peak | 0.33 × 10 | 3.33 per second = 200 a minute (S1) |
| Database queries, peak | 3.33 × 40 rooms | 133 per second |
| Storage | 20 bookings × 365 days × 600 bytes | 4.38 MB a year |
| Bandwidth, peak | 3.33 × 2 KB search answers; 0.17 × 2 MB photos | search 6.67 KB/s, photos 333 KB/s |

## Database questions per operation (our Laravel code, as of P07)

| Operation | Requests a second | Questions to PostgreSQL per request | Questions a second |
|---|---|---|---|
| GET /rooms/availability | 3.3 | 40 (one per room: hasOverlap) | 133 |
| POST /reservations | 0.33 | about 4 (guest lookup, guest insert, hasOverlap, save) | 1.3 |
| DELETE /reservations/{id} | 0.03 | 2 (find, save) | 0.07 |

The search is the busiest part: about 99% of all database questions. P09 later changed the booking path
(see docs/p09-protect-the-last-seat.md), so the POST row is the P07 count.

## Conclusion

The first thing that limits the app is the number of database questions per search. A cache for the search
is the first idea to try (P08), after measuring it.

## To measure later (written in P07, done in P08)

- p95 of 1,000 searches for 12-14 Nov, target <= 1 s (S1).
- Database queries per search (GET /stats).

## Measured (P08)

One user, two rooms, 1,000 searches one after another on a laptop (docs/cache-experiment.md):

- A search takes about 124 ms on average and 146 ms p95 without a cache (164 ms p95 with it). Most of that is the development
  server booting Laravel, not the database. S1 (p95 <= 1 s) is met.
- One search sends 2 database questions (one per room), exactly as estimated: 1,000 searches = 2,000 questions.
  With the cache: 8 questions (4 misses, because the run was longer than the 30 s TTL).
