# ADR-002: Cache the free-room search

Status: Accepted, 1 November 2026

## Context
Every search asks PostgreSQL once per room: 2,000 queries for 1,000 searches with
two rooms, and 133 a second at the estimated peak with 40 rooms (docs/capacity.md).
A search answer changes only when someone books or cancels. The booking rule (S2)
must never depend on an old copy.

## Decision
Cache-aside for GET /rooms/availability only, through Laravel's Cache (the file store on
the development Mac; CACHE_STORE=redis in production, no code change). The key is
availability:v{version}:{checkIn}:{checkOut}, the value is the free room numbers,
the TTL is 30 seconds. A successful booking or cancel raises availability:version.
POST and DELETE never read the cache: PostgreSQL is the source of truth.

## Alternatives considered
- No cache: always fresh and simplest, but every search costs one query per room.
- TTL only: simple, but a search can show a booked room for up to 30 seconds.
- Invalidation only: fresh after every change, but one missed invalidation keeps
  a wrong copy until the cache is cleared.

## Consequences
+ 1,000 searches for the same stay cost 2 reservation queries instead of 2,000
  (docs/cache-experiment.md).
+ If an invalidation is ever missed, the copy is wrong for at most 30 seconds.
- Every booking empties every cached search: in a busy sale the hit rate falls.
- The cache store is one more thing to run in production (Redis). If it is down,
  searches and bookings fail; there is no fallback to PostgreSQL yet.
- Two misses at the same moment both ask PostgreSQL.
- The counters of GET /stats live in the same cache store, so cache:clear resets them.

## Revisit when
Bookings, not searches, limit the app (P09), or the hit rate measured under
load (P23) is much lower than in docs/cache-experiment.md.
