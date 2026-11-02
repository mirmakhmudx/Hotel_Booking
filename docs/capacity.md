# Capacity estimate (P07)

Numbers come from the quality scenarios (S1) and a hotel with 40 rooms. They are estimates, not measurements.

## Assumptions

| What | Value | Where it comes from |
|---|---|---|
| Rooms | 40 | the hotel |
| Searches at the peak | 200 a minute (3.3 a second) | S1: 200 guests within one minute |
| Bookings at the peak | 1 booking for every 10 searches: 20 a minute | assumption |
| Cancellations | 2 a minute | assumption |

## Requests and database questions

| Operation | Requests a second | Questions to PostgreSQL per request | Questions a second |
|---|---|---|---|
| GET /rooms/availability | 3.3 | 40 (one per room: hasOverlap) | 133 |
| POST /reservations | 0.33 | about 4 (guest lookup, guest insert, hasOverlap, save) | 1.3 |
| DELETE /reservations/{id} | 0.03 | 2 (find, save) | 0.07 |

The search is the busiest part: about 99% of all database questions.

## Storage and traffic

- A reservation row is about 100 bytes (200 with indexes). At most about 7,300 reservations a year (40 rooms, 2-night stays): about 1.5 MB a year. Storage is not a problem.
- One search page (20 rooms) is about 0.8 KB of JSON: 3.3 a second is about 3 KB a second. Traffic is not a problem.

## Conclusion

The first thing that limits the app is the number of database questions per search. A cache for the search is the first idea to try (P08), after measuring it.

## Measured (P08)

One user, two rooms, 1,000 searches one after another on a laptop (docs/cache-experiment.md):

- A search takes about 124 ms on average and 146 ms p95 without a cache (164 ms p95 with it). Most of that is the development
  server booting Laravel, not the database. S1 (p95 <= 1 s) is met.
- One search sends 2 database questions (one per room), exactly as estimated: 1,000 searches = 2,000 questions.
  With the cache: 8 questions (4 misses, because the run was longer than the 30 s TTL).
