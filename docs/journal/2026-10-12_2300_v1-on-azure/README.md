# V1 on Azure: Postgres and App Service

## What was asked

> "v1 on azure, let's go"
>
> "done, we need to upload the code"
>
> "why didnt you tell me how to deploy man"

## What we did

1. **Decisions first:** current subscription, **App Service** (over Container Apps).
2. **Postgres** ([setup 04](../../setup/04-azure-postgres.md)):
   - The user wrote `infra/main.bicep`, reviewed in rounds: quoting, `@secure()`, unique names via `uniqueString`, nested child resources, outputs.
   - Germany West Central turned out to be restricted for this subscription, so everything moved to **Sweden Central**.
   - An empty `$MYIP` broke one firewall rule; `@minLength(7)` now catches it.
   - Server `psql-sahl-dev-sdc-vmi3r3`, migrated and seeded from the Mac.
3. **App Service** ([setup 05](../../setup/05-azure-app-service.md)):
   - Added to the Bicep: plan `asp-sahl-dev-sdc` (B1 Linux) and web app `app-sahl-dev-sdc-vmi3r3` (PHP 8.4, App Settings as `.env`).
   - The user's draft had several mistakes, which I fixed on request:
     - the Laravel `APP_KEY` was placed into Application Insights settings
     - `WEBSITE_RUN_FROM_PACKAGE` would have made `storage/` read-only
     - `minTlsVersion` was set to `PHP|8.4`
   - An `APP_KEY` was briefly a default in the Bicep and pushed to the public repo. It's burned and was never used.
4. **Laravel behind App Service:**
   - `trustProxies(at: '*')`
   - `infra/app-service/nginx.conf` (web root `public/`)
   - `startup.sh` (installs nginx config, `artisan optimize`)
   - `package.sh` (production zip)
5. **Deploy:**
   - I started the upload myself without explaining it. The user called that out, so I stopped it, explained Kudu and the deploy flow, and the user ran the deploy.
   - Live at **https://app-sahl-dev-sdc-vmi3r3.azurewebsites.net**.

## Decisions

- **Subscription:** current one for now (moving is one Bicep deploy).
- **Hosting:** App Service B1 Linux, PHP 8.4.
- **Region:** **Sweden Central**, because Postgres is restricted in Germany West Central, West Europe and Qatar Central for this subscription. Saved to project memory.
- **Network:** public Postgres endpoint with firewall (Mac IP + Azure services), SSL required. Private networking later.
- **Build on the Mac, not on Azure:** Kudu/Oryx build is off; the zip contains `vendor/` and `public/build/`.
- **Secrets are in App Settings for now.** They move to Key Vault with a managed identity next.
- **Working agreement reinforced:** I give the commands, the user runs Azure steps.

## Files

- `infra/main.bicep` (new; user-written, reviewed)
- `infra/app-service/{nginx.conf,startup.sh,package.sh}` (new)
- `bootstrap/app.php` (trust proxies), `.gitignore` (`/build`)
- `docs/setup/04-azure-postgres.md`, `docs/setup/05-azure-app-service.md` (new)
