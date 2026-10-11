# Entity-relationship diagram (ERD) v0.1

**When:** 2026-10-10 17:54 (UTC+3)

## What was asked

> “Can you generate the ERD for me?”

## What we did

- Drew the full data model as 10 Mermaid diagrams: organisation and identity, the service catalogue and applications, reviews and issuance, payments, appointments, audit and messaging, and the external mock systems.
- Set the conventions: UUIDv7 IDs, `timestamptz` in UTC, money in minor units, no soft deletes, `_ar`/`_en` columns.
- Listed decisions E1–E8 for you, each with a recommendation.

## Files

- [`docs/architecture/erd.md`](../../architecture/erd.md)
