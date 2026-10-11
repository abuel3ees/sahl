# The Azure Setup Playbook — Building a Web System on Azure, in the Right Order

> **Who this is for:** someone who has never set anything up on Azure.
> **What it answers:** *When you build a web app on Azure, what do you set up, in what order, why that order, and what matters at each step?*
> Every step explains the **what**, the **why now**, the **important settings**, the **common mistakes**, and **how Sahl does it**.
> Companion docs: `sahl-architecture-guide.md` (concepts), `azure-services-catalog.md` (what each service is), `bicep-guide.md` (how to write the code).

---

## Part 0 — The big idea: build from the ground up

### The house analogy

You don't build a house by starting with the furniture. You:

1. **Get the land and permits** (account, subscription, budget)
2. **Agree the rules** (naming, tagging, policies)
3. **Decide who gets keys** (access control)
4. **Install the alarm system and cameras** (logging) — *before* anyone moves in, so you record everything from day one
5. **Lay the foundations, roads, and pipes** (network)
6. **Install the safe** (Key Vault)
7. **Build the rooms** (database, storage, messaging)
8. **Move the people in** (deploy the app)
9. **Put up the front gate and guard** (Front Door + WAF)
10. **Inspect, insure, and maintain** (monitoring, backups, security review, cost review)

### Why the order matters — dependencies

Each layer **needs the previous ones to exist**:

```
Account & subscription
   └─► Budget (so spending is watched from minute one)
        └─► Naming + tags + policy (so everything created after follows the rules)
             └─► Access (RBAC) + deployment identity (so only the pipeline builds things)
                  └─► Logging (so every later resource can send its logs somewhere)
                       └─► Network (subnets must exist before anything plugs into them)
                            └─► Key Vault + Container Registry (shared services the app needs)
                                 └─► Managed identities + role assignments (app needs permission before it starts)
                                      └─► Data (DB, storage, cache, messaging — private, in the network)
                                           └─► Compute (the app, configured to reach the data)
                                                └─► App sign-in (Entra app registrations)
                                                     └─► Edge (Front Door + WAF + domain)
                                                          └─► Monitoring, backups, security & cost review
                                                               └─► Go live → Day-2 operations
```

**Classic beginner mistakes caused by wrong order:**
- Creating resources first, adding a budget later → surprise bill.
- Creating a database with public access "temporarily" before the network exists → it stays public.
- Deploying the app before its identity has permission to Key Vault → app crashes at startup.
- Adding logging last → no logs for the incident that happened in week one.
- Clicking things in the portal "just to test" → nobody knows what exists or why (**configuration drift**).

### Two layers: platform vs workload

Real organizations split responsibilities:

| | **Platform (landing zone)** | **Workload (application)** |
|---|---|---|
| Owned by | Central cloud/platform team | Application team |
| Contains | Management groups, policies, hub network, firewall, shared logging, DNS, identity setup | The app, its database, storage, app-specific network (spoke) |
| Changes | Rarely, carefully | Often |
| Sahl | Steps 1–9 (+ gov-profile hub) | Steps 10–19 |

In Sahl, **you play both teams** — but keep them separate in your head and in the code (`infra/platform` vs `infra/workload` thinking).

### Two ways to do every step

- **Portal (clicking)** — good for *learning and looking*. Never the way we create real things.
- **Code (Bicep + CLI, via pipeline)** — how real teams do it. Repeatable, reviewable, auditable.

> **The bootstrap problem:** a pipeline needs an identity and permissions *before* it can deploy anything — so a few first steps (account, budget, pipeline identity) are done **once by hand (CLI)**. After Step 8, the rule is: **if it's not in Git, it doesn't exist.**

### How this maps to Sahl's phases

- **Phase 1 (no Azure account):** you *write* the Bicep for every step and compile it locally.
- **Phase 2 (30-day free account):** you *execute* Steps 1→22 in order, mostly by running the pipeline.

---

## Part 1 — Plan before you touch Azure

### Step 0 — Write down the decisions that are expensive to change

**What:** a short planning sheet (in Sahl: the ADRs + architecture guide).
**Why first:** some choices can't be changed later without rebuilding — region, IP ranges, naming, tenant structure.

| Decide | Why it's hard to change later | Sahl's answer |
|---|---|---|
| **Region** | Resources can't be moved between regions easily; residency implications | Germany West Central (`gwc`) for PoC |
| **Environments** | Determines subscriptions, resource groups, pipelines | `dev` (always), `prod`/`gov` (bursts) |
| **Naming convention** | Many resource names can never be renamed — only deleted and recreated | `<type>-sahl-<env>-<region>` (e.g., `kv-sahl-dev-gwc`) |
| **Tagging standard** | Retro-tagging hundreds of resources is painful | `workload`, `environment`, `owner`, `data-classification`, `cost-center` |
| **IP address plan** | Overlapping ranges can never be connected (peering/VPN) | Hub `10.0.0.0/16`, dev spoke `10.20.0.0/16`, prod spoke `10.30.0.0/16`, shared `10.40.0.0/16` |
| **Identity model** | Tenants, who signs in where | Workforce tenant (employees, admins), External ID tenant (citizens) |
| **Data classification** | Drives encryption, networking, residency | Public / Internal / Restricted / Highly restricted |

**Common mistakes:** picking `10.0.0.0/16` for everything (then nothing can be peered); naming things `test1`, `myapp`; no tags.

---

## Part 2 — The account and subscription foundation

### Step 1 — Create the account and secure it

**What:** sign up for Azure. This automatically creates:
- your **user account** (the Microsoft account/email you signed up with),
- a **Microsoft Entra tenant** (often named "Default Directory"),
- a **subscription** (e.g., "Azure subscription 1" / "Free Trial"),
- and makes you **Owner** of that subscription and **Global Administrator** of the tenant.

**Why first:** nothing exists without it — and the account that owns everything must be the most protected thing you have.

**Important settings:**
1. **Turn on MFA** for your account immediately (Microsoft Authenticator app). An Azure owner account without MFA is the #1 way personal cloud accounts get hijacked (and used for crypto-mining on your bill).
2. Note your **Tenant ID** and **Subscription ID**: `az account show` after `az login`.
3. Rename the subscription to something meaningful (e.g., `sub-sahl-poc`) — the portal → Subscriptions → Rename.
4. Understand the **free account terms**: credit amount, expiry, spending limit (resources get disabled when credit runs out).

**In real organizations (📄):**
- Two **emergency "break-glass" accounts** with Global Admin, very long passwords stored offline, excluded from normal sign-in policies — in case MFA or federation breaks.
- Admins use a **separate admin account** from their daily email account.
- Subscriptions are created through an **Enterprise Agreement/Microsoft Customer Agreement**, not a credit card.

**Common mistakes:** no MFA; using the global admin account for daily work; not knowing which subscription the CLI is pointed at.

### Step 2 — Put guardrails on money (before creating anything)

**What:**
- A **budget** on the subscription (e.g., $150) with alerts at 50%, 80%, 100% (actual) and 100% (forecasted).
- **Cost anomaly alerts** (Cost Management) — "spending pattern changed suddenly."
- Bookmark **Cost Management → Cost analysis**.

**Why now:** money starts flowing the moment the first resource exists. Budgets **don't stop spending** — they **alert** you. (The free account's spending limit is the only hard stop.)

**Important settings:** alerts go to an email you actually read; budget scope = subscription; also later one budget per resource group for bursts.

**Sahl:** budget created by CLI on day one of Phase 2; later also captured in Bicep (`Microsoft.Consumption/budgets`, subscription scope).

**Common mistakes:** thinking a budget is a cap; forgetting resources that bill per hour while idle (Firewall, App Gateway, VPN Gateway, APIM classic tiers, Postgres while running).

### Step 3 — Organize: management groups, subscriptions, resource groups

**What:**
- **Management groups**: e.g., `mg-sahl` (root for the project) → children `mg-sahl-platform`, `mg-sahl-workloads`. Your subscription goes under one of them.
- **Resource providers**: register the ones you'll use (`Microsoft.App`, `Microsoft.DBforPostgreSQL`, `Microsoft.KeyVault`, `Microsoft.Network`, `Microsoft.OperationalInsights`, `Microsoft.ApiManagement`, …). The portal often does it automatically; the CLI/Bicep sometimes fail with `MissingSubscriptionRegistration` until you do.
- **Resource groups**: one per **lifecycle** — `rg-sahl-platform-gwc` (logging, shared), `rg-sahl-dev-gwc` (workload), `rg-sahl-shared-gwc` (mock national systems), `rg-sahl-gov-gwc` (burst, delete after).

**Why now:** policies and access (next steps) are assigned **at** these levels. You need the folders before you set rules on them.

**Rule of thumb for resource groups:** "Would I delete these things together?" → same group. Different owners or lifecycles → different groups.

**Common mistakes:** one giant resource group for everything; mixing dev and prod in one group; the resource group's own region doesn't matter much, but keep it equal to its resources for clarity.

### Step 4 — Naming and tagging standard (applied from now on)

**What:** a written convention + a Bicep `names` variable (see `bicep-guide.md` Lesson 4) so names are generated, never typed by hand.

| Resource | Prefix | Example | Notes |
|---|---|---|---|
| Resource group | `rg` | `rg-sahl-dev-gwc` | |
| Virtual network | `vnet` | `vnet-sahl-dev-gwc` | |
| Subnet | `snet` | `snet-apps` | |
| Key Vault | `kv` | `kv-sahl-dev-gwc` | 3–24 chars, globally unique |
| Storage account | `st` | `stsahldevx7k2m9` | lowercase, no hyphens, globally unique |
| PostgreSQL | `psql` | `psql-sahl-dev-gwc` | globally unique DNS name |
| Container registry | `cr` | `crsahldevx7k2m9` | alphanumeric only |
| Container Apps env | `cae` | `cae-sahl-dev-gwc` | |
| Container App | `ca` | `ca-sahl-citizen-dev-gwc` | |
| Log Analytics | `log` | `log-sahl-dev-gwc` | |
| App Insights | `appi` | `appi-sahl-dev-gwc` | |
| API Management | `apim` | `apim-sahl-dev-gwc` | globally unique |
| Front Door | `afd` | `afd-sahl-dev` | global |
| Managed identity | `id` | `id-sahl-api-dev-gwc` | |

(Prefixes follow Microsoft's Cloud Adoption Framework abbreviations.)

**Tags on every resource:** `workload=sahl`, `environment=dev`, `owner=<you>`, `data-classification=<level>`, `cost-center=portfolio`.

**Why now:** everything created after this follows the standard — and Step 5 enforces it.

### Step 5 — Governance: Azure Policy

**What:** assign policies at the management group or subscription:

| Policy | Effect | Why |
|---|---|---|
| Allowed locations = `germanywestcentral` (+ global) | **Deny** | Residency: nothing gets created elsewhere — even by mistake |
| Require tag `workload` and `environment` on resource groups | **Deny** | Every cost is attributable |
| Inherit tags from resource group | **Modify** | Resources get tags automatically |
| Storage accounts should disable public network access | **Audit** → later **Deny** | No public data stores |
| Key Vault should have purge protection | **Audit** | Recoverability |
| Diagnostic settings to Log Analytics | **DeployIfNotExists** | Logging can't be forgotten |
| Allowed SKUs (block very expensive ones) | **Deny** | Protect the credit |

**Why now:** policy only affects things created/changed **after** it's assigned (existing resources are evaluated for compliance but not blocked retroactively). Assign early, and every later step is automatically held to the rules.

**Important:** start with **Audit**, see what would fail, then switch to **Deny** — otherwise you block your own deployments without understanding why.

**Sahl:** decision O6 (recommended). Policy assignments live in Bicep at subscription/management-group scope.

**Common mistakes:** Deny policies that block the global services (Front Door, Entra) — allow `global` location; assigning policies after everything exists.

### Step 6 — Access control: who can do what

**What:** design and apply **Azure RBAC** (Chapter A.4).

| Who | Role | Scope | Why |
|---|---|---|---|
| You (admin account) | Owner | Subscription | Needed to set up; in real life use PIM to make this temporary |
| GitHub Actions pipeline identity (Step 8) | Contributor + limited "User Access Administrator" (conditions) or a custom role | Workload resource groups | The pipeline deploys; humans don't |
| Developers (in a team) | Reader | Subscription | Look, don't touch production |
| App managed identities (Step 12) | Data-plane roles only (e.g., Key Vault Secrets User) | Single resources | Least privilege |

**Principles:**
- Assign roles to **groups**, not individuals (easier to manage and review).
- **Narrowest role, smallest scope.**
- Remember the **control plane vs data plane** split — managing a resource ≠ reading its data.
- Prefer **Entra RBAC on data services** (Key Vault RBAC, Storage data roles, Postgres Entra auth) over keys/passwords.

**In real organizations (📄):** PIM for all privileged roles; quarterly **access reviews**; separation between people who build infrastructure and people who see citizen data.

**Common mistakes:** giving everyone Owner; giving the app's identity Contributor on the subscription "to make it work."

### Step 7 — Central logging (before any workload)

**What:**
- One **Log Analytics workspace** (`log-sahl-platform-gwc` or per environment) with a sensible **retention** (e.g., 30 days for operational logs) and a **daily ingestion cap** (protects the credit).
- Export the subscription's **Activity Log** (who created/changed/deleted what) to the workspace.
- A policy (Step 5) or Bicep convention that every resource gets **diagnostic settings** pointing here.
- (Later) a storage account for long-term **audit/security logs** with immutability.

**Why now:** "cameras before people move in." Everything from Step 8 onward can immediately send logs. Resources created *before* logging exists have gaps in their history.

**Common mistakes:** no ingestion cap (Log Analytics is a classic bill surprise); logging everything at verbose level; forgetting the Activity Log (the most important audit trail of Azure itself).

### Step 8 — The deployment identity (the bootstrap) — after this, no more clicking

**What:** let **GitHub Actions** deploy to Azure **without any stored secret**, via **OIDC workload identity federation**:

1. Create an **app registration** (or a **user-assigned managed identity**) in Entra: `sahl-github-deployer`.
2. Add **federated credentials** that trust specific GitHub contexts:
   - `repo:<you>/sahl:environment:dev` → can deploy dev
   - `repo:<you>/sahl:environment:prod` → can deploy prod (and that GitHub environment requires **your approval**)
   - optionally `repo:<you>/sahl:pull_request` with **Reader** only → run `what-if` on PRs
3. Give it RBAC roles (Step 6) on the right scopes.
4. In GitHub: store only **IDs** (tenant ID, subscription ID, client ID) as variables — **not secrets**.
5. A workflow uses `azure/login` with OIDC → runs `az deployment … create`.

**Why now:** from here on, every resource is created by the pipeline from Git. This is the moment your environment becomes **reproducible and auditable** (Git history = change history).

**Sahl:** done once by CLI script in Phase 2 (documented in `infra/bootstrap/README.md`).

**Common mistakes:** creating a client **secret** and pasting it into GitHub (works, but it's exactly what we avoid); one identity with Owner on everything; no approval gate on prod.

---

## Part 3 — Shared platform services

### Step 9 — The network

**What (lean profile):**
1. **VNet** `vnet-sahl-dev-gwc` with address space `10.20.0.0/16`.
2. **Subnets** sized for growth (you can't easily resize a subnet that's in use):

| Subnet | Range | For | Notes |
|---|---|---|---|
| `snet-apps` | `10.20.0.0/23` | Container Apps environment / App Service VNet integration | Container Apps needs a dedicated subnet with a minimum size ⚠ Verify; delegated |
| `snet-functions` | `10.20.2.0/24` | Functions VNet integration | delegated |
| `snet-pe` | `10.20.3.0/24` | Private endpoints | many PEs, one IP each |
| `snet-postgres` | `10.20.4.0/24` | PostgreSQL Flexible Server (VNet integration) | delegated to Postgres |

3. **NSGs** on each subnet: deny by default what isn't needed.
4. **Private DNS zones** for every PaaS service that gets a private endpoint, **linked to the VNet**:
   - `privatelink.vaultcore.azure.net` (Key Vault)
   - `privatelink.blob.core.windows.net` (Blob)
   - `privatelink.postgres.database.azure.com` (Postgres)
   - `privatelink.servicebus.windows.net` (Service Bus)
   - `privatelink.azurecr.io` (Container Registry)
   - … (each service documents its zone name)

**Why now:** everything after this **plugs into** the network. Creating data services first means either public access (bad) or recreating them later.

**Gov profile adds (bursts):** hub VNet (`10.0.0.0/16`) with `AzureFirewallSubnet`, `AzureBastionSubnet`, DNS resolver subnet; **peering** hub↔spoke; **route table** sending `0.0.0.0/0` to the firewall; private DNS zones moved to the hub.

**Common mistakes:** subnets too small; forgetting delegation; private endpoint created but DNS zone not linked → app still resolves the public IP → "connection refused" (the #1 private networking bug).

### Step 10 — Key Vault

**What:** `kv-sahl-dev-gwc` with:
- `enableRbacAuthorization: true` (Azure RBAC, not legacy access policies),
- **soft delete + purge protection** on,
- **public network access disabled** + **private endpoint** in `snet-pe`,
- diagnostic settings → Log Analytics (who accessed which secret/key, when).

Inside it (later steps): document-signing **keys** per ministry, any unavoidable secrets (e.g., the mock payment gateway's webhook secret), TLS certificates.

**Why now:** many later services need a place for keys/certificates/secrets at creation time.

**Common mistakes:** purge protection off (a deleted vault's name and keys can be lost/blocked); public access left on; giving the app `Key Vault Administrator` instead of `Key Vault Secrets User` / `Crypto User`.

**Important nuance:** with public access disabled, **you** (from your laptop) can't read the vault either — that's intended. For admin tasks you'd connect through the private network (gov profile: Bastion/VPN) or temporarily allow your IP (lean profile, documented exception).

### Step 11 — Container Registry

**What:** `crsahldev…` — stores the Laravel image (and Functions/mocks images if containerized).
- Admin user **disabled**; pull via **managed identity** (`AcrPull` role).
- Basic SKU in lean; **Premium** needed for private endpoints (gov profile).

**Why now:** the app can't be deployed until there's an image to pull.

---

## Part 4 — The workload

### Step 12 — Identities for the application

**What:** **user-assigned managed identities**, created *before* the app:
- `id-sahl-portal-dev-gwc` — used by both Laravel surfaces and workers
- `id-sahl-functions-dev-gwc` — used by Functions

Then **role assignments** (least privilege):

| Identity | Role | On |
|---|---|---|
| portal | `AcrPull` | Container Registry |
| portal | `Key Vault Secrets User` | Key Vault |
| portal | `Storage Blob Data Contributor` | documents container only |
| portal | `Azure Service Bus Data Sender` | Service Bus namespace |
| portal | Postgres **Entra admin / role** | PostgreSQL (configured in the DB) |
| functions | `Key Vault Crypto User` | signing keys only |
| functions | `Storage Blob Data Contributor` | issued + audit containers |
| functions | `Azure Service Bus Data Receiver` | Service Bus |

**Why user-assigned (not system-assigned) here:** the identity exists **before** the app, so permissions are ready the moment the app starts — no "first deploy fails because permissions aren't there yet" race. It also survives app recreation.

**Note:** role assignments can take a few minutes to take effect ("RBAC propagation").

### Step 13 — Data services

Create each one **private from birth**, with **diagnostic settings** and **Entra authentication**:

**13a. PostgreSQL Flexible Server**
- SKU Burstable `B1ms` (dev); General Purpose + **zone-redundant HA** (gov/prod).
- **Private access** (VNet integration into `snet-postgres`) — choose at creation; switching later is limited ⚠ Verify.
- **Microsoft Entra authentication enabled**; add the portal's managed identity as a database role; minimize or disable password auth.
- Backup retention (7–35 days), **point-in-time restore**; no geo-redundant backup (residency) unless an exception is approved.
- TLS enforced.
- **Stop the server** when not used (dev) to save credit — note: Azure auto-starts stopped servers after 7 days ⚠ Verify.

**13b. Storage account**
- `allowBlobPublicAccess: false`, TLS 1.2, HTTPS only, **shared key access disabled** (force Entra auth) where possible.
- **Public network access disabled** + private endpoint for `blob`.
- Containers: `documents` (uploads), `issued` (signed PDFs), `audit` (**immutability policy**: time-based retention; locked only in gov profile).
- **Soft delete** + **versioning** for blobs.
- **Lifecycle rules** (audit → Cool → Archive).

**13c. Redis** (decision O8) — private, Entra or access-key auth stored in Key Vault, for Laravel sessions/cache/locks.

**13d. Messaging** (decision O2) — Service Bus namespace / Event Grid topics; queues/topics + **dead-letter** handling; private endpoint if SKU allows (Service Bus Premium) — otherwise documented exception.

**13e. AI** — AI Search + Azure OpenAI deployments; managed identity access; check **model availability in region**.

**Why now:** the app's configuration needs these endpoints and must have permission to them at startup.

**Common mistakes:** "I'll make it private later"; storing DB passwords in app settings; forgetting diagnostic settings; leaving dev databases running 24/7.

### Step 14 — Compute: deploy the application

**What (Container Apps example; App Service is analogous):**
1. **Container Apps environment** in `snet-apps`, connected to Log Analytics.
2. Container apps (same image, different config):
   - `ca-sahl-citizen-dev-gwc` — `APP_SURFACE=citizen`, **external** ingress (but only reachable via Front Door — Step 17)
   - `ca-sahl-backoffice-dev-gwc` — `APP_SURFACE=backoffice`, **internal-only** ingress (lean profile: temporary IP restriction for you)
   - `ca-sahl-worker-dev-gwc` — runs `queue:work`, no ingress, **scales on queue length**
   - **Container Apps job** for the scheduler (`schedule:run` every minute) or a dedicated worker.
3. **Configuration:** environment variables for non-secret settings; secrets as **Key Vault references** (never plain values); the user-assigned identity attached.
4. **Health probes** (`/health/live`, `/health/ready`) so Azure only sends traffic to healthy replicas.
5. **Scaling rules:** min/max replicas (min 0 in dev to save money — accept cold starts; min 1 in prod).
6. **Revisions:** each deploy creates a new revision; you can split traffic or roll back.

**Laravel specifics for cloud:**
- No local files: storage disk → Blob; sessions/cache → Redis; logs → stderr (collected by Azure).
- `php artisan config:cache`, `route:cache` at image build; migrations run as a **separate one-off job** in the pipeline (not on every container start — multiple replicas would race).
- `APP_KEY` and other secrets from Key Vault.
- Trust the proxy headers (Front Door) so URLs and client IPs are correct.

**Why now:** everything it needs (network, identity, data, image) exists.

**Common mistakes:** running migrations at container startup; storing uploads on the container filesystem (lost on restart); no health probes; secrets in plain env vars.

### Step 15 — Application sign-in (Entra app registrations)

**What:**
- **Workforce tenant:** app registration `sahl-backoffice` — redirect URI(s), **app roles** (`PlatformAdmin`, `EntityAdmin`, `Reviewer`, `Approver`, `ServiceCenterAgent`, `Auditor`), groups per ministry, **group/role claims** in tokens, "assignment required" = yes (only assigned employees can sign in).
- **External ID tenant:** create the **external tenant**, app registration `sahl-citizen`, **user flow** (sign-in), **custom OIDC identity provider** = mock National Authentication Service, claim mapping (`qid`, names, nationality, `acr`).
- **Credentials for Laravel (confidential client):** certificate in Key Vault, or federated credential with the app's managed identity ⚠ Verify support for your setup — avoid plain client secrets where possible.
- **Security defaults / Conditional Access** (MFA for employees).

**Why now:** you need the app's real URLs (Step 14/17) for redirect URIs, and the app must exist to test the sign-in.

**Common mistakes:** redirect URI mismatch (the #1 sign-in error — must match exactly, including trailing slash and http/https); accepting tokens from the wrong tenant; forgetting "assignment required" so any employee in the tenant can sign in.

### Step 16 — Integration layer (APIM)

**What:** API Management instance; import the **Commerce Registry** and **National Registry** mock APIs and the **payment webhook** endpoint; **products/subscriptions** per consumer; policies: `validate-jwt`, rate limits, IP filtering for the gateway webhook, request logging to App Insights.

**Why now:** the mock systems and the app exist to put behind it. (APIM provisioning can take 30–60+ minutes on some tiers — start it early in the session.)

### Step 17 — The edge: Front Door, WAF, domain, TLS

**What:**
1. **Front Door** profile + endpoint; **origin** = citizen Container App.
2. **WAF policy** (managed rule set + bot protection) in **Prevention** mode after testing in **Detection** mode.
3. **Lock the origin**: the app must accept traffic **only from Front Door** — check the `X-Azure-FDID` header and/or restrict to the `AzureFrontDoor.Backend` service tag; in the gov profile use **Private Link** so the app has no public endpoint at all.
4. **Custom domain** (optional): DNS records + **managed TLS certificate** (free, auto-renewing).
5. Caching rules for static assets (Vite build output).

**Why now:** the edge points **at** the app; the app must exist first. And it's the last door you open — so it's opened only when everything behind it is secured.

**Common mistakes:** Front Door in front, but the app still reachable directly on its own URL (bypassing the WAF); WAF in Prevention mode from day one blocking legitimate requests without investigation.

---

## Part 5 — Make it operable, safe and affordable

### Step 18 — Monitoring and alerting

**What:**
- **Application Insights** connected to both surfaces, workers, Functions (OpenTelemetry); correlation IDs.
- **Alerts** (with an action group → your email):
  - HTTP 5xx rate > threshold
  - p95 latency > threshold
  - Service Bus dead-letter count > 0
  - Failed payment webhook signature verifications
  - Key Vault access denied spikes
  - Budget alerts (Step 2)
  - Service Health incidents in your region
- **Availability test** pinging the public portal.
- **Workbook/dashboard**: requests, errors, queue depth, SLA overdue reviews.

**Why here:** you now have something to watch. (Logs have been flowing since Step 7; this step turns them into **signals**.)

**Common mistakes:** alerts nobody receives; too many noisy alerts (people learn to ignore them); no alert on DLQs (silent failures).

### Step 19 — Backups and recovery — and *test* them

**What:**
- Confirm Postgres **PITR** retention; **do a test restore** to a new server and connect to it.
- Blob **soft delete + versioning** — delete a test file and recover it.
- Key Vault soft delete — delete and recover a test secret.
- Write a **runbook**: "how to restore the database to 10:15 yesterday."
- Record **RPO/RTO** achieved vs targets (Chapter 19).

**Why:** a backup that was never restored is a hope, not a backup.

### Step 20 — Security review

**What:**
- **Defender for Cloud** (free tier): secure score, recommendations — fix or document each.
- **Azure Advisor**: security + reliability recommendations.
- **Policy compliance** view: anything non-compliant?
- Check: any resource with **public network access**? any **keys/passwords** in app settings? any identity with **too much access**?
- Run an OWASP ZAP baseline scan against the public portal (dev).
- Update the **STRIDE threat model** with what was actually built.

### Step 21 — Cost review

**What:** Cost analysis grouped by resource and tag; compare against the budget; identify always-on costs; scale down / stop / delete; record the **cost per hour of the gov profile** for the case study.

### Step 22 — Go-live checklist

Before calling an environment "done" (or before a demo):

**Security**
- [ ] MFA on all admin accounts; no standing Owner except where necessary
- [ ] No data service with public network access (or documented exception)
- [ ] App reachable only through Front Door + WAF; back-office internal only
- [ ] No secrets in code, app settings, or pipeline variables — managed identities / OIDC / Key Vault references
- [ ] Key Vault: RBAC, purge protection, diagnostic logs
- [ ] Least-privilege role assignments reviewed

**Reliability**
- [ ] Health probes configured
- [ ] Backups configured **and a restore tested**
- [ ] Autoscale min/max set deliberately
- [ ] Dead-letter queues monitored

**Operations**
- [ ] Every resource sends diagnostics to Log Analytics
- [ ] Alerts route to a real inbox; tested once
- [ ] Runbooks: deploy, rollback, restore, rotate keys, tear down
- [ ] Everything in Bicep; `what-if` shows **no drift** (nothing created by hand)

**Governance & cost**
- [ ] All resources tagged; policy compliance green (or exceptions documented)
- [ ] Budget alerts active; idle costs understood
- [ ] ADRs updated with what changed during the build

---

## Part 6 — Day 2: keeping it running

"Day 1" is building it. **Day 2** is everything after — where most real-world effort goes.

| Task | How often | What it means |
|---|---|---|
| Review costs | Weekly | Anything new or growing? |
| Review alerts & failed jobs | Daily (real), per session (PoC) | DLQs, errors, failed webhooks |
| Patch & upgrade | Monthly | PaaS patches the OS for you — **you** still update PHP, Laravel, npm packages, base images, and API versions in Bicep |
| Rotate secrets/keys | Per policy (e.g., yearly) | Key Vault rotation policies; fewer secrets = less rotation |
| Access reviews | Quarterly (real) | Who still needs which role? |
| Restore test | Quarterly (real) | Prove backups still work |
| Policy/Defender review | Monthly | New recommendations, compliance drift |
| Read Azure Updates | Monthly | Retirements of services/SKUs/API versions you use |
| Tear down bursts | After every demo | `az group delete -n rg-sahl-gov-gwc --yes` |

---

## Appendix A — The "quick way" vs the "government way"

To show *why* this playbook has 22 steps: here's what a tutorial does vs what Sahl does.

| | Quick tutorial ("deploy a web app in 5 minutes") | Government-grade (this playbook) |
|---|---|---|
| Create resources | Portal clicks | Bicep via pipeline |
| Region | Whatever's default | Chosen for residency, enforced by policy |
| Database | Public endpoint, password in app settings | Private endpoint/VNet, Entra auth, no password |
| Secrets | Connection strings in config | Managed identities; Key Vault references |
| Network | None | VNet, subnets, NSGs, private DNS, (firewall) |
| Entry | App's default `*.azurecontainerapps.io` URL | Front Door + WAF; origin locked |
| Identity | Local logins | Entra federation, app roles, MFA |
| Logs | Defaults | Central workspace, diagnostics on everything, immutable audit |
| Cost control | Hope | Budgets, anomaly alerts, policy-limited SKUs, teardown |
| Change control | None | Git history + PR review + prod approval |

Both "work." Only one would pass a government security review.

## Appendix B — Step ↔ Bicep module ↔ Sahl phase

| Step | Done by | Bicep (written in Phase 1) |
|---|---|---|
| 1 Account & MFA | Portal (once) | — |
| 2 Budget | CLI first, then Bicep | `infra/platform/budget.bicep` |
| 3 Mgmt groups, RGs | Bicep (subscription/MG scope) | `infra/main.bicep` |
| 4 Naming & tags | Bicep convention | `names` variable / `infra/shared/naming.bicep` |
| 5 Policy | Bicep | `infra/platform/policy.bicep` |
| 6 RBAC (humans) | Bicep | `infra/platform/rbac.bicep` |
| 7 Logging | Bicep | `infra/modules/monitoring.bicep` |
| 8 GitHub OIDC identity | **CLI script (bootstrap)** | `infra/bootstrap/` |
| 9 Network | Bicep | `infra/modules/network.bicep` (+ `gov/hub-network.bicep`) |
| 10 Key Vault | Bicep | `infra/modules/keyvault.bicep` |
| 11 Registry | Bicep | `infra/modules/registry.bicep` |
| 12 App identities + roles | Bicep | `infra/modules/identities.bicep` |
| 13 Data services | Bicep | `postgres.bicep`, `storage.bicep`, `redis.bicep`, `messaging.bicep`, `ai.bicep` |
| 14 Compute | Bicep + pipeline (image) | `infra/modules/compute.bicep` |
| 15 Entra app registrations | CLI/Graph scripts (or Bicep Graph extension ⚠ Verify) | `infra/identity/` |
| 16 APIM | Bicep | `infra/modules/apim.bicep` |
| 17 Front Door + WAF | Bicep | `infra/modules/frontdoor.bicep` |
| 18 Monitoring & alerts | Bicep | `infra/modules/alerts.bicep` |
| 19–22 Reviews | You | Runbooks in `docs/runbooks/` |

---

## Self-test

1. Why does the budget come before the first resource?
2. Why is logging set up before the network and the app?
3. What is the "bootstrap problem" and which step solves it?
4. Why use **user-assigned** managed identities created before the app?
5. Why must data services be private "from birth"?
6. What's the #1 cause of private endpoint failures?
7. Why shouldn't Laravel run migrations on container startup?
8. How do you stop users from bypassing Front Door's WAF by calling the app's own URL?
9. What's the difference between Day 1 and Day 2, and which takes more effort over a system's life?
