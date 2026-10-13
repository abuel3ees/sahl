# Postgres in Docker (local)

## What was asked

> "let's just do the commit and pgsql, then we have a bunch of shtuff"
>
> "do compose i already have docker on the machine, and yes"
>
> "looks like i dont have docker" → chose **A** (OrbStack)

## What we did

- Found that Docker Desktop had been deleted (Homebrew entries left, no `docker` command). The user installed **OrbStack**. Steps are in [setup guide 03](../../setup/03-docker-postgres-local.md).
- Added `compose.yaml`:
  - service `pgsql` running `postgres:17-alpine`, the same major version as Azure Database for PostgreSQL
  - port 5432, a named volume and a health check
  - an init script that creates `sahl_testing` on the first start
- Switched `.env` and `.env.example` to `pgsql` (`sahl`/`sahl`/`secret`).
- Switched `phpunit.xml` from in-memory SQLite to the `sahl_testing` database.
- `migrate:fresh --seed` ran cleanly on Postgres. **All 134 tests pass on Postgres** without code changes.
- Updated `CLAUDE.md` (Running locally) and the "Not in V1" table in `docs/v1/ministry-of-interior.md`, which also had stale rows for features now built.

## Decisions

- **Docker engine: OrbStack** (user's choice, option A), over Docker Desktop, Colima or Homebrew Postgres.
- **Tests run on Postgres too**, not SQLite, so tests behave like production. Trade-off: the container must be running to run tests.
- **Only the database is containerised.** PHP and Vite stay on the Mac (no Sail) to keep it simple. Containerising the app itself comes with Azure Container Apps.

## Files

- `compose.yaml` (new)
- `.env.example`, `.env` (not committed), `phpunit.xml`
- `CLAUDE.md`, `docs/v1/ministry-of-interior.md`
- `docs/setup/03-docker-postgres-local.md` (new)
