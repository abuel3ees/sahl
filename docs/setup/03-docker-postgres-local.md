# 03 · Docker (OrbStack) and local Postgres

**Done:** 2026-10-12, on the Mac (Apple silicon, macOS 26.6). No Azure involved, and nothing to redo when switching Azure accounts. Redo this only on a new Mac.

**Goal:** run the database the way Azure will, with **PostgreSQL 17** (the version of Azure Database for PostgreSQL Flexible Server), in a container instead of a SQLite file.

---

## Words used here

| Term | Meaning |
|---|---|
| **Container** | A packaged, isolated program with everything it needs (here: Postgres). It starts the same on any machine |
| **Image** | The template a container starts from (`postgres:17-alpine`) |
| **Docker engine** | What runs containers. On a Mac it's a small Linux VM, provided by an app like OrbStack or Docker Desktop |
| **Docker Compose** | Starts the containers described in `compose.yaml` with one command |
| **Volume** | Storage that outlives the container, so the data survives restarts |

---

## Step 1: Install a Docker engine (OrbStack)

**Starting point:** Docker Desktop had been deleted, but Homebrew still listed `docker`, `docker-completion` and `docker-desktop`. There was no working `docker` command.

**Options considered:**
- **OrbStack** (chosen): fast, light, free for personal use.
- Docker Desktop: heavier.
- Colima: command line only, more setup.
- Postgres from Homebrew without Docker: simplest, but no container practice.

1. Remove the stale entries and install OrbStack:
   ```bash
   brew uninstall --cask docker-desktop; brew uninstall docker docker-completion; brew install --cask orbstack
   ```
2. Open **OrbStack** from Applications once, choose **Docker**, and allow its prompts. It installs the `docker` command itself.
3. Check:
   ```bash
   docker compose version     # Docker Compose version v5.5.0
   docker info --format '{{.ServerVersion}} {{.OperatingSystem}}'   # 29.8.2 OrbStack
   ```

**Result:** Docker Compose v5.5.0 on Docker engine 29.8.2 (OrbStack).

## Step 2: Start Postgres

In the project folder:

```bash
docker compose up -d        # starts container sahl-pgsql-1 in the background
docker compose ps           # STATUS should say (healthy) after a few seconds
```

What `compose.yaml` sets up:

| Setting | Value |
|---|---|
| Image | `postgres:17-alpine` |
| Port | `127.0.0.1:5432` (change with `FORWARD_DB_PORT` in `.env` if taken) |
| Database / user / password | `sahl` / `sahl` / `secret` (local only; taken from `DB_*` in `.env`) |
| Data | volume `sahl_sahl-pgsql`, which survives `docker compose down` |
| Test database | `sahl_testing`, created automatically on the **first** start (empty volume) |

**Check:**
```bash
docker compose exec pgsql psql -U sahl -d sahl -Atc "select datname from pg_database"
# postgres, sahl, sahl_testing, template0, template1
```

## Step 3: Point Laravel at it

In `.env` (`.env.example` already has the same values):
```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sahl
DB_USERNAME=sahl
DB_PASSWORD=secret
```

In `phpunit.xml`, tests use their own database, so `migrate:fresh` in tests never wipes your dev data:
```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="sahl_testing"/>
```

Then:
```bash
php artisan config:clear
php artisan migrate:fresh --seed
php artisan test --compact
```

**Result:** all 12 migrations and the seeders ran on Postgres (8 demo requests: 6 submitted, 1 approved, 1 rejected). **All 134 tests passed on Postgres** with no code changes.

---

## Everyday commands

| To… | Run |
|---|---|
| Start the database (after a reboot) | `docker compose up -d`. OrbStack must be running |
| Stop it (data kept) | `docker compose down` |
| Wipe it completely (data and `sahl_testing` gone; recreated on next `up`) | `docker compose down -v` |
| Open a SQL prompt | `docker compose exec pgsql psql -U sahl -d sahl` |
| See its logs | `docker compose logs -f pgsql` |

| If you see… | It means… |
|---|---|
| `SQLSTATE[08006] … Connection refused` | The container isn't running: open OrbStack, then `docker compose up -d` |
| `database "sahl_testing" does not exist` | The volume was created before the init script existed. `docker compose down -v && docker compose up -d` |
| `port is already allocated` | Something else uses 5432. Set `FORWARD_DB_PORT=5433` and `DB_PORT=5433` in `.env` |

---

## Checklist for a new Mac

- [ ] Install OrbStack (`brew install --cask orbstack`), open it once, check `docker compose version`
- [ ] `docker compose up -d`, wait for `(healthy)`
- [ ] `.env`: set `DB_CONNECTION=pgsql` and the other `DB_*` values as above
- [ ] `php artisan migrate:fresh --seed` and `php artisan test --compact`
