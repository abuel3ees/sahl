# 05 · App Service: Sahl live on Azure (V1 on Azure, step 2)

**Done:** 2026-10-12, current subscription, resource group `rg-sahl-dev-sdc` (Sweden Central). Builds on [04](04-azure-postgres.md).

**Result:** **https://app-sahl-dev-sdc-vmi3r3.azurewebsites.net** serves Sahl from Postgres in Azure. Public pages work. Sign-in comes in the next step (Entra redirect URIs and secrets).

**Cost:** App Service plan **B1** (Linux) is about $13 a month while it exists. You pay for the plan, not the app, and stopping the app doesn't stop the charge. Delete the plan, or scale it to F1, to stop paying.

---

## Words used here

| Term | Meaning |
|---|---|
| **App Service plan** (`Microsoft.Web/serverfarms`) | The server you rent: size and price. `reserved: true` makes it Linux |
| **Web app** (`Microsoft.Web/sites`) | The site on the plan. **Not a child** of the plan: `serverFarmId` points at it, like a foreign key |
| **App Settings** | Environment variables injected into PHP: the app's `.env` on Azure |
| **Kudu / SCM site** | `<app>.scm.azurewebsites.net`: the deployment engine and admin console of every app. Receives zip deploys, shows files and logs, gives SSH |
| **Startup command** | A script App Service runs every time the container starts |

## Step 1: Infrastructure (Bicep)

`infra/main.bicep` gained:
- `var suffix` (shared by both names)
- `param appKey` (`@secure()`, no default)
- the **plan** `asp-sahl-dev-sdc` (B1, `kind: 'linux'`, `reserved: true`)
- the **web app** `app-sahl-dev-sdc-<suffix>`:
  - runtime: `PHP|8.4`, `alwaysOn`, `httpsOnly`
  - security: TLS 1.2, FTP disabled
  - `appCommandLine` pointing at the startup script
  - `appSettings` for `APP_*`, `LOG_CHANNEL=stderr` and `DB_*`. `DB_HOST` is read from the Postgres resource
- the output `appUrl`

```bash
php artisan key:generate --show      # a NEW key for Azure; keep it in the password manager
MYIP=$(curl -s https://api.ipify.org); echo "IP: $MYIP"
az deployment group create -g rg-sahl-dev-sdc -f infra/main.bicep -p allowedClientIp=$MYIP
# prompts: the DB admin password (same as before) and the app key
```

**Mistake to avoid:** an `APP_KEY` was once written into `main.bicep` as a default and pushed to the public GitHub repo. That key is burned; it was never deployed. **A `@secure()` parameter never has a default.**

## Step 2: Make Laravel work behind App Service

These live in the repo and are reused by every deploy.

| File | Why |
|---|---|
| `bootstrap/app.php`: `trustProxies(at: '*')` | App Service terminates HTTPS and forwards HTTP. Without it Laravel builds `http://` asset URLs and browsers block them |
| `infra/app-service/nginx.conf` | The PHP image serves `/home/site/wwwroot`; Laravel needs `public/` as the web root. Also blocks dotfiles |
| `infra/app-service/startup.sh` | On every start: install the nginx config, reload nginx, create the storage folders, `php artisan optimize` (caches config with the App Settings) |
| `infra/app-service/package.sh` | Builds `build/sahl.zip`: `npm run build`, copies the repo without `.env`, `node_modules`, tests or local caches, runs `composer install --no-dev` |

## Step 3: Deploy the code

```bash
infra/app-service/package.sh
az webapp deploy -g rg-sahl-dev-sdc -n app-sahl-dev-sdc-vmi3r3 --type zip --src-path build/sahl.zip
az webapp log tail -g rg-sahl-dev-sdc -n app-sahl-dev-sdc-vmi3r3      # watch it start; Ctrl+C to quit
```

`az webapp deploy` uploads the zip to **Kudu**, which unzips it into `/home/site/wwwroot` and restarts the app. `startup.sh` then runs.

**What went wrong the first time:** `package.sh`'s rsync excluded `build/`, which also matched `public/build/`, so the zip had no CSS or JS. Patterns are now anchored to the repo root (`/build/`).

**Result (2026-10-12):**
- `/`, `/login`, `/verify`, `/docs/ministry-of-interior`, `/developers` and `/up` return 200 in about 0.5–1 s.
- Assets load over https.
- Sessions are written to Postgres.

## Everyday commands

| To… | Run |
|---|---|
| Deploy new code | `infra/app-service/package.sh` then `az webapp deploy …` (above) |
| See live logs (Laravel logs to stderr) | `az webapp log tail -g rg-sahl-dev-sdc -n app-sahl-dev-sdc-vmi3r3` |
| Restart | `az webapp restart -g rg-sahl-dev-sdc -n app-sahl-dev-sdc-vmi3r3` |
| Shell inside the app container | Portal → the web app → **SSH**, or `https://app-sahl-dev-sdc-vmi3r3.scm.azurewebsites.net` |
| Run a migration on Azure | From the Mac, as in [04](04-azure-postgres.md) step 4 (`DB_HOST=… php artisan migrate --force`) |

---

## Checklist for the new account

- [ ] [04](04-azure-postgres.md) done (Postgres server, `sahl` database, tables)
- [ ] Generate a new `APP_KEY`, then `az deployment group create` (plan + web app)
- [ ] `infra/app-service/package.sh`, then `az webapp deploy`
- [ ] Open `appUrl`: landing page in Arabic, `/up` returns 200
