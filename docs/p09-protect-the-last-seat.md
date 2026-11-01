# P09 · Protect the last seat

Brief: repair a conflicting database update without overselling capacity.
Our "seat" is a **room-night**: the invariant is R3/S2, *a room never has two Confirmed bookings on the same night*.

| Brief example | Our project |
|---|---|
| 20 seats, 19 accepted | R7 free for 12-14 Nov (0 Confirmed shares a night) |
| Alex and Emma request the last seat | Maya and Omar request R7, 12-14 Nov |
| One accepted, the other gets Full | One `Accepted R101`, the other `Unavailable` (HTTP 409) |
| Accepted count is 20 | `countConfirmed(R7, 12-14 Nov) = 1` |

## 1. Interleaving trace (the bug, version 1)

`BookingService::reserve` was: `hasOverlap` ... gap ... `save`. The gap is `pause->wait()` in the demo and network/CPU time in real life.

| Time (ms) | Maya | Omar | Confirmed rows for R7 12-14 |
|---|---|---|---|
| 0 | `hasOverlap(R7, 12-14)` reads 0 Confirmed -> false | | 0 |
| 10 | (waiting) | `hasOverlap(R7, 12-14)` reads 0 Confirmed -> false | 0 |
| 500 | `save` -> R101 | (waiting) | 1 |
| 510 | | `save` -> R102 | **2** |

Both read the same "remaining capacity" (free) before either wrote. A check that is true when read can be false by the time we act: **check-then-act**. Invariant broken: R3 / S2.

## 2. Three repairs (all in `EloquentReservationStore::placeIfFree`)

| | Atomic conditional insert | Optimistic version check | Short row-lock transaction |
|---|---|---|---|
| Idea | `INSERT ... SELECT ... WHERE NOT EXISTS (clash)` : check and save are one statement | read `rooms.version`; save in a transaction that does `UPDATE rooms SET version = v+1 WHERE version = v`; 0 rows = someone got there first | `SELECT ... FOR UPDATE` on the room row, check, save, commit |
| Who waits? | nobody | nobody (loser retries) | the second request waits for the first |
| On conflict | the loser gets 0 rows -> `Unavailable` | re-read, re-check (max 3 attempts), then `Unavailable`; `TooMuchContention` if it never settles | the loser checks after the winner committed -> `Unavailable` |
| Invariant it protects | "no clash **at the moment of the statement**" | "nobody booked this room between my read and my write" (any booking of the room bumps the version) | "only one request at a time inside the check+save of this room" |
| Weak spot | two statements at the same instant can both pass `NOT EXISTS` (neither sees the other's uncommitted row). **Only complete together with the exclusion constraint.** | retries cost work under heavy contention; version bumps even for non-overlapping dates of the same room | holds a lock while it works: keep it short, never send email inside; `lock_timeout` 5 s |
| Good when | conflicts are rare, you want the simplest SQL | conflicts are rare, you cannot hold locks | conflicts are common (a sale), correctness matters most |

**Last line of defence (all strategies):** the migration adds `EXCLUDE USING gist (room_id WITH =, daterange(check_in, check_out, '[)') WITH &&) WHERE (status = 'Confirmed')`. It uses the same half-open nights as `DateRange`, ignores Cancelled rows (R4), and allows check-out day = next check-in (R3). Even buggy application code cannot leave two bookings.

**Found while testing (real evidence, not in the pre-check):** with the constraint on and two truly simultaneous inserts, PostgreSQL can report `deadlock detected` (SQLSTATE 40P01) instead of the clean `23P01` refusal; it rolls one request back, and that test run was several seconds slower (consistent with PostgreSQL's default `deadlock_timeout` of 1 s, not measured separately). The database still prevented the double booking (`confirmed = 1`), but a request that gets 40P01 must be repeated, not shown as an error. `atomicInsert` therefore retries on 40P01 (max 3), and the race helper reports it as `DeadlockVictim`. `lock` and `optimistic` take the room's row first, so their inserts are serialized and should not meet on the constraint at the same instant (reasoned, and no deadlock was seen in their runs, but not proven).

Default strategy: `lock` (`HOTEL_CONCURRENCY=unprotected|atomic|optimistic|lock` in `.env`).
Known gap: `TooMuchContention` is not mapped to HTTP yet (it would be a 500; a 503 + `Retry-After` is the natural next step, with an `openapi.yaml` entry).
Design trade-off: `rooms.version` is a column on a Rooms table used by Reservations code. A separate table owned by Reservations would be cleaner.

## 3. How to run it

```bash
docker compose up -d            # or your local PostgreSQL
php artisan migrate             # adds rooms.version and the constraint
```
If `migrate` stops with "pairs of Confirmed reservations already share a night", those rows are left over from the old race demo. Run `php artisan hotel:demo` (clears all reservations), then migrate again. `CREATE EXTENSION btree_gist` needs a user allowed to create extensions (the database owner is enough on PostgreSQL 13+).

```bash
php artisan test                                          # all tests, including the real two-process races
php artisan test --filter=ConcurrentBookingTest           # only the real races
php artisan hotel:race --strategy=all --without-constraint --pairs=100   # only the strategy protects
php artisan hotel:race --strategy=all --pairs=100                        # strategy + constraint (S2: 0 of 100)
php artisan hotel:demo --race                             # old Fiber demo of the v1 bug
```

Where the evidence comes from:
- `ConcurrentBookingTest` and `hotel:race`: **real** - each pair is two PHP processes, two database connections, released together, 100-200 ms between check and save.
- `BookingServiceTest` race tests: Fibers on one connection. They prove the v1 gap and that the constraint refuses, **not** that a lock works.
- `ConcurrencyStrategyInMemoryTest`: **simulation** (no database). Do not report it as proof of a repair.

## 4. Evidence

### 4a. Pre-check made while preparing the code (NOT your project's tests)
PostgreSQL 16, plain-PDO re-implementation of the same SQL, two processes per pair, 200 ms gap, 20 pairs:

| Strategy | constraint OFF: double bookings | constraint ON: double bookings |
|---|---|---|
| unprotected | 20 / 20 | 0 / 20 (second guest stopped by the database) |
| atomic insert | **20 / 20** | 0 / 20 (second guest stopped by the database) |
| optimistic | 0 / 20 | 0 / 20 |
| row lock | 0 / 20 | 0 / 20 |

Read it like this: the constraint alone already prevents the oversell, but the loser gets a database error instead of a clean answer; the atomic insert only becomes correct *because of* the constraint (here it maps the refusal to `Unavailable`); optimistic and lock keep the invariant on their own and give the loser a clean `Unavailable`.

### 4b. Your runs (fill in from your own machine - do not copy 4a)
