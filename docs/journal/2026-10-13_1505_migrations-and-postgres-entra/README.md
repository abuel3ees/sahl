# Migrations on deploy, and Postgres sign-in with the managed identity

## What was asked

> "let's just do migrations and postgres, then we can do the app insights"
>
> "Yes A A"  (migrations in `startup.sh`; an owner group role for least privilege)

## What we did

1. **Migrations:** `startup.sh` runs `php artisan migrate --force --isolated` on every start. Every deploy restarts the app, so code and schema go live together, and GitHub never needs database access. Guide: [setup 07](../../setup/07-github-actions-deploy.md), step 5.
2. **Entra sign-in on Postgres** (Bicep): `authConfig` turns on Entra sign-in next to the password, and the user is the server's Entra admin. The user's sign-in name contains their email, so it comes from `SAHL_ADMIN_UPN` instead of the public repo. The user asked to skip a deploy script for now.
3. **One-time SQL** (`infra/postgres/01-roles.sql` as the user, `02-ownership.sql` as `sahladmin`):
   - a role `app-sahl-dev-sdc-vmi3r3` for the web app's identity
   - a group role `sahl_owner` that owns every table, with the app, `sahladmin` and the user as members, each defaulting to `SET ROLE sahl_owner`
   - The server was inspected first (Postgres 17.11; schema `public` owned by `azure_pg_admin`) to make sure the grants would work.
4. **Laravel:**
   - `app/Database/ManagedIdentityToken.php` gets tokens from App Service's identity endpoint and caches them.
   - `EntraPostgresConnector`, bound as `db.connector.pgsql`, uses the token as the password when `DB_ENTRA_AUTH=true`.
   - 5 tests, one of them against the real local database. 139 tests pass.
5. **Switch:**
   - The code shipped first (PR #5). Then the Bicep set `DB_USERNAME=app-sahl-dev-sdc-vmi3r3` and `DB_ENTRA_AUTH=true`, and removed `DB_PASSWORD`.
   - Verified from the database side: sessions are written with the token.

Guide: [setup 08](../../setup/08-postgres-entra-sign-in.md).

## Decisions

- **Migrations run at start-up on App Service**, not from the GitHub runner.
- **Least privilege via `sahl_owner`.** The app isn't a server admin, and the data was kept.
- **Password sign-in stays on for now.** Turning it off (removing `sahladmin` and `db-password`) is the next step.

## Files

- `infra/app-service/startup.sh`, `infra/main.bicep`, `infra/main.bicepparam`
- `infra/postgres/01-roles.sql`, `infra/postgres/02-ownership.sql` (new)
- `app/Database/ManagedIdentityToken.php`, `app/Database/EntraPostgresConnector.php` (new)
- `app/Providers/AppServiceProvider.php`, `config/database.php`, `.env.example`
- `tests/Feature/ManagedIdentityDatabaseTest.php` (new)
- `docs/setup/07-github-actions-deploy.md`, `docs/setup/08-postgres-entra-sign-in.md` (new), `docs/setup/05-azure-app-service.md`, `CLAUDE.md`
