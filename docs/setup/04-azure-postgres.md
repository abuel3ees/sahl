# 04 · Azure Database for PostgreSQL (V1 on Azure, step 1)

**Started:** 2026-10-12, on the current (temporary) subscription "Azure subscription 1". **Redo** on the new account: it's one Bicep deploy, but **check the region first** (step 1).

**Goal:** a real Postgres 17 server in Azure that Sahl will use once it runs on App Service. Local development keeps using Docker ([03](03-docker-postgres-local.md)).

**Cost:** Burstable `Standard_B1ms` plus 32 GB storage is about $15–20 a month if left running. To pause the compute charge (Azure restarts it automatically after 7 days):
```bash
az postgres flexible-server stop  -g rg-sahl-dev-sdc -n psql-sahl-dev-sdc-vmi3r3
az postgres flexible-server start -g rg-sahl-dev-sdc -n psql-sahl-dev-sdc-vmi3r3
```

---

## Decisions

| Decision | Choice | Why |
|---|---|---|
| Subscription | Current one, for now | Everything is Bicep, so moving later is one deploy |
| App hosting (step 2) | **App Service** (over Container Apps) | Fewest new concepts for a first deploy |
| Region | **Sweden Central** (`sdc`) | Germany West Central, West Europe and Qatar Central are **restricted** for this subscription (see step 1). The app and the database must share a region |
| Network | Public endpoint + firewall (my IP + "Azure services") | "Designed strict, deployed lean". Private VNet integration later. SSL is enforced by Azure |
| Availability | No HA, no geo-redundant backup, 7-day backups | Cheapest. Production would enable both |

## Step 0: Budget alert (portal)

**Cost Management → Budgets → + Add**: scope *Azure subscription 1*, name `budget-sahl`, monthly, **$20**, alerts at 50/80/100% (actual) to your email.

## Step 1: Check the region, register providers, create the resource group

**What went wrong the first time:** the deployment to Germany West Central failed with

> ParameterOutOfRange: The value of the 'Version' should be in: []

The empty list means "this subscription may not create Postgres servers here". The capability list said *"Provisioning is restricted in this region."* Check before choosing a region:

```bash
az postgres flexible-server list-skus -l swedencentral --query "[0].{versions: supportedServerVersions[].name, reason: reason}"
# versions must include 17, and reason must be empty
```

Result on 2026-10-12:
- **Restricted:** germanywestcentral, westeurope, qatarcentral.
- **Available:** swedencentral, northeurope, francecentral, polandcentral, italynorth, switzerlandnorth, uksouth, uaenorth.

```bash
az provider register -n Microsoft.DBforPostgreSQL
az provider register -n Microsoft.Web
az group create -n rg-sahl-dev-sdc -l swedencentral
```

(The first attempt's `rg-sahl-dev-gwc` was deleted. `rg-sahl-identity` holds the citizen tenant: **leave it**.)

## Step 2: The Bicep file `infra/main.bicep`

Written by the user and reviewed. It contains:
- **Params:**
  - `location` (from the resource group)
  - `administratorLogin` (`sahladmin`)
  - `administratorPassword` (`@secure()`, no default)
  - `allowedClientIp` (`@minLength(7)`)
- **Server name:** `psql-sahl-dev-sdc-` plus 6 characters of `uniqueString(resourceGroup().id)`. Server names are public DNS names and must be unique worldwide.
- **Server:** `Microsoft.DBforPostgreSQL/flexibleServers@2025-08-01` with:
  - B1ms Burstable, version 17, 32 GB
  - 7-day backups, no geo-redundancy, HA disabled
  - public access
- **Children:**
  - database `sahl`
  - firewall rules `AllowMyMac` (your IP) and `AllowAzureServices` (`0.0.0.0`, which means other Azure services)
- **Output:** `serverFqdn`.

```bash
az bicep build -f infra/main.bicep          # must print nothing
```

## Step 3: Deploy (your own terminal, because it prompts for the password)

```bash
MYIP=$(curl -s https://api.ipify.org); echo "IP: $MYIP"
az deployment group what-if -g rg-sahl-dev-sdc -f infra/main.bicep -p allowedClientIp=$MYIP
az deployment group create  -g rg-sahl-dev-sdc -f infra/main.bicep -p allowedClientIp=$MYIP
```

Keep the admin password in a password manager. Re-deploying with a different password **changes** it.

**What went wrong the second time:** `InvalidParameterValue: Invalid value given for parameter StartIpAddress`. `$MYIP` was empty, because it had been set in another terminal session. The server, database and other rule had already been created; re-running with the IP set added the missing rule. The fix in code was `@minLength(7)`, so an empty IP now fails before anything is deployed.

**Result (2026-10-12):**
- Server **`psql-sahl-dev-sdc-vmi3r3`**, address `psql-sahl-dev-sdc-vmi3r3.postgres.database.azure.com`.
- State Ready, Postgres 17, B1ms.
- Port 5432 reachable from the Mac.

Read the output again at any time:
```bash
az deployment group show -g rg-sahl-dev-sdc -n main --query properties.outputs.serverFqdn.value -o tsv
```

---

## Checklist for the new account

- [ ] Budget alert
- [ ] `list-skus` shows version 17 in the chosen region (pick another if restricted, and update the region code in `serverName`)
- [ ] Register `Microsoft.DBforPostgreSQL` and `Microsoft.Web`, then create `rg-sahl-dev-<code>`
- [ ] `az bicep build`, `what-if`, `create` with `MYIP` set; note the server name and save the password
