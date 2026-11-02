# Cache experiment: the free-room search

PostgreSQL is the source of truth. The cache (Laravel Cache; the file store on this Mac, Redis in
production) keeps copies of search answers for 30 seconds.

## Measurements

1,000 searches for 12-14 Nov, one after another, with tools/measure.php.
Times from one laptop; yours will differ.

| Run | Searches | Database queries | Cache hits | Average | p95 |
|---|---|---|---|---|---|
| No cache | 1,000 | 2,000 | 0 | 124.2 ms | 145.7 ms |
| Cache-aside, TTL 30 s | 1,010 | 8 | 1,006 | 128.7 ms | 164.1 ms |

S1 asks for p95 <= 1 second: both runs meet it (145.7 ms and 164.1 ms), with two rooms and one user.
The cache removes 1,998 of 2,000 database queries. With 40 rooms it would remove
39,960 of 40,000.

## Stale result (Step 5, before invalidation)

1. Empty cache. Search 12-14 Nov: R7 and R8 (a miss: the answer is cached for 30 s).
2. Maya books R7 for 12-14 Nov: 201 R101.
3. Search again within 30 s: R7 and R8. STALE: R7 is booked.
4. Omar asks for R7 for 13-15 Nov: 409 Unavailable. Booking reads PostgreSQL,
   not the cache, so the booking rule still holds.

## After invalidation (Step 6)

The same four requests: the third answer is R8 only. A successful booking or cancel raises
availability:version, so the next search builds a new key, misses and asks PostgreSQL.
Test test_a_booking_and_a_cancel_make_the_next_search_fresh fails without the invalidate line.

## Hot key and the next bottleneck (Step 7)

- New Year: everyone searches 31 Dec - 2 Jan, so one key answers almost every search:
  availability:v{version}:2026-12-31:2027-01-02.
- Every booking raises the version, so in a busy sale each booking throws that copy
  away and the next search asks PostgreSQL again.
- Searches now cost the cache, not PostgreSQL. What is left for PostgreSQL is
  POST /reservations: the questions must be exact. That is the next bottleneck: P09.

## Note on the cache run
The run took 128.7 s (the dev server boots Laravel on every request, about 125 ms), longer than the 30 s TTL,
so the copy expired and was fetched again 3 more times: 4 misses x 2 questions = 8 database queries instead of 2.
The /stats difference counted 1,010 searches: 10 came from outside the tool.

## Note on the cache run
The run took 128.7 s (the dev server boots Laravel on every request, about 125 ms), longer than the 30 s TTL,
so the copy expired and was fetched again 3 more times: 4 misses x 2 questions = 8 database queries instead of 2.
The /stats difference counted 1,010 searches: 10 came from outside the tool.

## What the numbers say (and do not say)
- Database queries: 2,000 without the cache, 8 with it (4 misses x 2 rooms). The cache is not "faster by 2,000 queries" in time here:
  the average went from 124.2 ms to 128.7 ms, and p95 from 145.7 ms to 164.1 ms (slightly worse).
- Why: the time is dominated by `php artisan serve`, which boots Laravel on every request (about 120 ms), and the file cache
  and the counters add a little work to every request. With two rooms and one user the database is not the bottleneck yet.
- So the gain of the cache is the load it takes off PostgreSQL (99.6% fewer questions), not the speed of one request on this laptop.
  At the estimated peak with 40 rooms (133 questions a second, docs/capacity.md) that load is what would matter.
- The cache run took 128.7 s, longer than the 30 s TTL, so the copy expired and was fetched 3 more times (4 misses, 8 queries).
