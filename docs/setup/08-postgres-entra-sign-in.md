# 08 · Postgres sign-in with the managed identity (no DB password for the app)

**Started:** 2026-10-13, server `psql-sahl-dev-sdc-vmi3r3`, resource group `rg-sahl-dev-sdc`. Builds on [06](06-key-vault-sign-in.md) and [07](07-github-actions-deploy.md).

**Goal:** the web app signs in to Postgres as its own managed identity, with Entra tokens. Laravel no longer needs `DB_PASSWORD`. You sign in from the Mac with your Entra account the same way.

---

## Words used here

| Term | Meaning |
|---|---|
| **Entra authentication** (`authConfig.activeDirectoryAuth`) | Postgres accepts an Entra access token as the password. Password sign-in can stay on next to it |
| **Entra admin** (`flexibleServers/administrators`) | An Entra identity that can create Postgres roles for other Entra identities. Here: you |
| **`pgaadauth_create_principal_with_oid`** | Azure's function (in the `postgres` database) that creates a Postgres role tied to an Entra object ID |
| **Owner group role** (`sahl_owner`) | A role that can't log in and owns every table. The app, `sahladmin` and you are members, and each session starts with `SET ROLE sahl_owner`. Only a table's owner can `ALTER` it, so this lets whoever runs a migration change any table |
| **Token audience** | Tokens for Postgres are requested for `https://ossrdbms-aad.database.windows.net` (`az … --resource-type oss-rdbms`) |

## Decisions

- **Owner group role, least privilege.** This was chosen over making the app an Entra admin of the server. The app can work only in the `sahl` database, and the existing data stayed.
- **Password sign-in stays on** until the app works with tokens. It is turned off in a later step.

## Step 1: Entra sign-in on the server, you as Entra admin (Bicep)

`infra/main.bicep` gained:
- `authConfig` on the server: Entra **and** password sign-in on, with the tenant set
- the child `administrators` resource, named after your object ID: `principalName` = your sign-in name, `principalType: 'User'`
- the param `adminPrincipalName`. `main.bicepparam` reads it from `SAHL_ADMIN_UPN`, because your sign-in name contains your email address and the repo is public

```bash
export MYIP=$(curl -s https://api.ipify.org)
export SAHL_ADMIN_UPN=$(az ad signed-in-user show --query userPrincipalName -o tsv)
az deployment group create -g rg-sahl-dev-sdc --parameters infra/main.bicepparam -o none
```

There's no `psql` on the Mac, so test with the one in the local Docker Postgres container. The token is the password:

```bash
docker compose up -d
docker compose exec \
  -e PGPASSWORD="$(az account get-access-token --resource-type oss-rdbms --query accessToken -o tsv)" \
  pgsql psql "host=psql-sahl-dev-sdc-vmi3r3.postgres.database.azure.com dbname=postgres user=$SAHL_ADMIN_UPN sslmode=require" \
  -c "select current_user"
```

**Result:** one row with your sign-in name.

## Step 2: Roles and ownership (one-time SQL)

Two scripts in `infra/postgres/`:

| Script | Runs as | Does |
|---|---|---|
| `01-roles.sql` | **You** (Entra token), starting in `postgres` | 1. Creates the role `app-sahl-dev-sdc-vmi3r3` for the web app's identity (object ID `30582149-e3b9-4886-a741-ed08c128024f`, from `az webapp identity show … --query principalId`)<br>2. Creates `sahl_owner` and gives it `USAGE, CREATE` on schema `public`<br>3. Makes the app, `sahladmin` and you members<br>4. Sets the app and you to `set role = sahl_owner` in `sahl` |
| `02-ownership.sql` | **sahladmin** (password from the vault) | 1. Moves every table, and with it its sequences and indexes, to `sahl_owner`<br>2. Sets `sahladmin`'s own sessions to `sahl_owner`<br>3. Prints the owners |

```bash
H=psql-sahl-dev-sdc-vmi3r3.postgres.database.azure.com
docker compose exec -T \
  -e PGPASSWORD="$(az account get-access-token --resource-type oss-rdbms --query accessToken -o tsv)" \
  pgsql psql "host=$H dbname=postgres user=$SAHL_ADMIN_UPN sslmode=require" < infra/postgres/01-roles.sql
docker compose exec -T \
  -e PGPASSWORD="$(az keyvault secret show --vault-name kv-sahl-dev-sdc-vmi3r3 -n db-password --query value -o tsv)" \
  pgsql psql "host=$H dbname=sahl user=sahladmin sslmode=require" < infra/postgres/02-ownership.sql
```

**Why it's split:** in Postgres 16 and later, only a table's owner (`sahladmin`) can give the table away. Only the role's creator (you) can change the app role's session defaults.

**Result (checked 2026-10-13):**
- 15 tables, 8 sequences and 38 indexes are owned by `sahl_owner`.
- The app, `sahladmin` and you are members of `sahl_owner`.
- Your session in `sahl` runs as `sahl_owner`.
- All the data is still there.

## Step 3: Laravel signs in with a token

| File | What |
|---|---|
| `app/Database/ManagedIdentityToken.php` | Asks App Service's local identity endpoint (`IDENTITY_ENDPOINT`, header `X-IDENTITY-HEADER: $IDENTITY_HEADER`) for a token for `https://ossrdbms-aad.database.windows.net`. Caches it in the file cache until 5 minutes before it expires. Reads the two env vars at runtime, never into the config cache |
| `app/Database/EntraPostgresConnector.php` | Laravel's Postgres connector. When `entra_auth` is on, it uses the token as the password on every new connection |
| `AppServiceProvider::register()` | Binds `db.connector.pgsql` to it. Laravel looks connectors up by that name |
| `config/database.php` | `'entra_auth' => env('DB_ENTRA_AUTH', false)` on the `pgsql` connection |
| `tests/Feature/ManagedIdentityDatabaseTest.php` | Endpoint call and caching, the error outside Azure, and that the token really is the password sent to Postgres (against the local test database) |

With `DB_ENTRA_AUTH` unset, nothing changes, so this code can be deployed before the switch.

## Step 4: Switch the App Settings (Bicep)

*Only after step 3 is live on Azure.*

In the web app's `appSettings`:
- `DB_USERNAME` is the site name, `app-sahl-dev-sdc-vmi3r3` (the Postgres role from step 2)
- `DB_ENTRA_AUTH` is `true`
- `DB_PASSWORD` is removed

```bash
az deployment group create -g rg-sahl-dev-sdc --parameters infra/main.bicepparam -o none
```

Changing App Settings restarts the app. `startup.sh` then runs the migrations with the token too.

## Migrations and seeds from the Mac (from now on)

Your Entra token replaces the `sahladmin` password:

```bash
DB_HOST=psql-sahl-dev-sdc-vmi3r3.postgres.database.azure.com DB_DATABASE=sahl DB_SSLMODE=require \
DB_USERNAME="$SAHL_ADMIN_UPN" \
DB_PASSWORD="$(az account get-access-token --resource-type oss-rdbms --query accessToken -o tsv)" \
php artisan db:seed --class=SomeSeeder --force
```
