# The Azure Services Catalog — What Everything Is, and What Sahl Uses

> Azure has 200+ services. You don't need all of them — but an architect must know **what exists**, so they can say *"we considered X and chose Y because…"*.
> This catalog explains each important service from zero, then says whether Sahl uses it.
> Read **Chapter A (Azure from absolute zero)** of the architecture guide first.

---

## How to read this catalog

Each service has:
- **What it is** — in plain words, with an analogy where it helps.
- **Use it when** — the typical reason teams pick it.
- **Sahl** — one of:

| Mark | Meaning |
|---|---|
| ✅ **Used** | Part of the PoC, deployed in the lean (everyday) profile |
| 🏛 **Gov profile** | Deployed only in the expensive "government-grade" bursts for demos |
| ❓ **Open decision** | A candidate — you'll decide in a later step |
| 📄 **Documented** | Not deployed, but named in our docs as what production would use |
| ❌ **Not used** | Not relevant to Sahl — with the reason |

**⚠ Verify**: Microsoft renames and retires services often. Names here are current to the best of our knowledge; check the docs before quoting one in an interview.

### The "shape" of a web system — use this to place every service

```
            USERS (citizens, employees, other ministries)
                              │
   ┌──────────────────────────▼──────────────────────────┐
   │ EDGE: DNS, Front Door, WAF, CDN, DDoS               │  how traffic gets in
   ├──────────────────────────────────────────────────────┤
   │ IDENTITY: Entra ID, External ID, managed identities │  who you are
   ├──────────────────────────────────────────────────────┤
   │ COMPUTE: App Service / Container Apps / Functions   │  where code runs
   ├──────────────────────────────────────────────────────┤
   │ INTEGRATION: APIM, Service Bus, Event Grid          │  how parts talk
   ├──────────────────────────────────────────────────────┤
   │ DATA: PostgreSQL, Redis, Blob Storage, AI Search    │  where data lives
   ├──────────────────────────────────────────────────────┤
   │ NETWORK: VNet, subnets, private endpoints, firewall │  the private roads
   ├──────────────────────────────────────────────────────┤
   │ SECURITY: Key Vault, Policy, Defender               │  locks and rules
   ├──────────────────────────────────────────────────────┤
   │ OPERATIONS: Monitor, Log Analytics, Cost Mgmt       │  seeing and paying
   └──────────────────────────────────────────────────────┘
```

---

## 1. Management & governance — "running Azure itself"

These aren't things your app *uses*; they're how you **organize, control, and pay for** Azure.

### Azure Resource Manager (ARM) — ✅ Used
**What it is:** the single front door for managing Azure. Every click in the portal, CLI command, and Bicep deployment becomes an ARM request. ARM checks permissions and policies, then tells the right service to act.
**Sahl:** everything goes through it — you never "choose" it, but you must understand it.

### Management groups — ✅ Used (simple)
**What it is:** folders *above* subscriptions. Policies and access granted on a management group apply to every subscription under it.
**Use it when:** an organization has many subscriptions (one per team/environment).
**Sahl:** we'll create a small hierarchy (e.g., `mg-sahl` → our subscription) mainly to *practice* assigning policy at the right level. A real government has a deep tree (platform, landing zones, sandbox, decommissioned).

### Subscriptions — ✅ Used
**What it is:** the billing and access boundary. Costs, quotas, and many permissions are per subscription.
**Sahl:** one free-account subscription. In production, dev and prod would be **separate subscriptions** (stronger isolation).

### Resource groups — ✅ Used
**What it is:** folders for resources that share a lifecycle. Delete the folder → delete everything inside.
**Sahl:** `rg-sahl-dev-gwc`, `rg-sahl-gov-gwc` (bursts), `rg-sahl-shared-gwc` (mock national systems). Burst cleanup = delete one resource group.

### Azure Policy — ✅ Used (recommended in Chapter 18; your decision O6)
**What it is:** rules that Azure enforces automatically. "Resources may only be created in Germany West Central." "Storage accounts must not allow public access." Can **deny**, **audit**, or **auto-fix** (deploy missing settings).
**Use it when:** you want standards enforced, not just documented.
**Sahl:** allowed locations (residency story), required tags, deny public network access on data services. Free.

### Cost Management + Budgets — ✅ Used
**What it is:** see what you've spent, by service/tag/resource group; set **budgets** that email you at 50/80/100%.
**Sahl:** a budget is the **first** thing we create — before any resource.

### Azure Advisor — ✅ Used (just look at it)
**What it is:** free recommendations on cost, security, reliability, performance ("this VM is idle", "enable soft delete").
**Sahl:** check it after deploying; good for the Well-Architected review.

### Azure Resource Graph — ✅ Used (queries)
**What it is:** a fast query engine over all your resources (KQL). "List every storage account without a tag."
**Sahl:** used occasionally to audit what exists.

### Tags & resource locks — ✅ Used
**What they are:** tags = labels on resources; locks = "CanNotDelete"/"ReadOnly" protection.
**Sahl:** tags everywhere; locks on the audit storage account and Key Vault in the gov profile.

### Azure Service Health — ✅ Used (alerts)
**What it is:** tells you when *Azure itself* has an outage or planned maintenance affecting your resources.
**Sahl:** one alert rule — free.

### Azure Blueprints — ❌ Not used
**What it is:** an older way to package policies + templates + RBAC. **Being deprecated** in favor of Template Specs and Deployment Stacks ⚠ Verify.
**Why not:** retired direction; Bicep + Policy cover it.

### Template Specs / Deployment Stacks — 📄 Documented
**What they are:** Template Specs = store Bicep/ARM templates *inside* Azure for reuse. Deployment Stacks = manage a set of resources as one unit, optionally blocking manual changes and cleaning up removed resources.
**Sahl:** we deploy from Git via GitHub Actions; Deployment Stacks mentioned as an option for drift protection.

### Azure Lighthouse — ❌ Not used
**What it is:** lets a service provider manage *other organizations'* Azure tenants (e.g., a vendor managing a ministry's environment).
**Why not:** single tenant. **Interesting fact:** very relevant in GCC government, where integrators often operate ministries' environments.

### Azure Arc — ❌ Not used (📄 mentioned)
**What it is:** manage servers, Kubernetes clusters, and databases **outside Azure** (on-prem, other clouds) as if they were Azure resources — same policy, monitoring, RBAC.
**Why not:** no on-prem estate. **Interview relevance:** high — governments with on-prem registries use Arc for hybrid governance.

### Azure Automation / Update Manager — ❌ Not used
**What they are:** run scripts on a schedule (runbooks); patch VMs.
**Why not:** we have no VMs; scheduled tasks run in our app/Functions.

---

## 2. Identity — "who are you, what can you do"

### Microsoft Entra ID (formerly Azure Active Directory) — ✅ Used
**What it is:** Microsoft's cloud identity directory for **employees** (workforce). Users, groups, app registrations, sign-in logs, MFA, Conditional Access.
**Sahl:** the **workforce tenant** for ministry employees; app roles (`Reviewer`, `Approver`…) and groups for entity scoping. Also where Azure's own admins (you) sign in.

### Microsoft Entra External ID — ✅ Used
**What it is:** Entra for **customers/citizens** (CIAM). A separate "external tenant" with sign-up/sign-in flows, branding, social and **custom OIDC identity providers**.
**Sahl:** citizens sign in through it, federated to our **mock National Authentication Service**.

### Azure AD B2C — ❌ Not used
**What it is:** the previous customer-identity product.
**Why not:** being phased out for new customers in favor of External ID ⚠ Verify.

### Managed identities — ✅ Used (everywhere)
**What they are:** identities Azure gives to *resources* (an app, a function) so they can access other Azure services **without passwords**. *System-assigned* (lives and dies with the resource) or *user-assigned* (standalone, shareable).
**Sahl:** the Laravel app, workers, and Functions use them for Key Vault, Storage, Postgres, Service Bus.

### Workload identity federation — ✅ Used
**What it is:** lets something *outside* Azure (GitHub Actions) prove who it is with its own token — no secret stored.
**Sahl:** GitHub Actions deploys to Azure this way.

### Privileged Identity Management (PIM) — 📄 Documented
**What it is:** admins get powerful roles **only when needed, for a limited time, with approval and justification**. Requires Entra ID P2 licensing.
**Sahl:** described in the security design; may try with a trial license if available.

### Conditional Access — ❓ Maybe (licensing)
**What it is:** sign-in rules: "require MFA for the back-office", "block sign-ins from outside allowed countries". Requires Entra ID P1.
**Sahl:** used if our tenant has P1 (trial); otherwise documented. **Security defaults** (free) give basic MFA.

### Microsoft Entra Verified ID — 📄 Documented (interesting!)
**What it is:** issue and verify **verifiable credentials** (digital, cryptographically signed claims a person holds in a wallet — like a digital diploma).
**Why only documented:** out of scope, but a strong "future" talking point: governments are exploring digital credentials for certificates and IDs.

### Microsoft Entra Domain Services — ❌ Not used
**What it is:** managed old-style Active Directory (LDAP, Kerberos) for legacy apps.
**Why not:** we have no legacy apps needing it.

---

## 3. Compute — "where code runs"

### Virtual Machines (VMs) — ❌ Not used
**What it is:** rent a whole computer; you manage the OS, patches, everything (IaaS).
**Use it when:** legacy software, special OS needs, full control.
**Why not:** PaaS removes patching and most ops work. We'll *explain* VMs, not run them.

### Virtual Machine Scale Sets — ❌ Not used
**What it is:** a group of identical VMs that autoscale.
**Why not:** same as VMs.

### App Service (Web Apps) — ❓ Open decision O1
**What it is:** a managed platform to run web apps — upload code or a container; Azure handles servers, OS, scaling, TLS. Pay for an **App Service Plan** (the underlying machines).
**Use it when:** classic web apps/APIs; teams want the simplest PaaS.
**Sahl:** one of two candidates for the Laravel app.

### Azure Container Apps — ❓ Open decision O1
**What it is:** run containers without managing Kubernetes. Scales to zero, scales on HTTP or queue length (KEDA), revisions, internal-only environments, jobs (cron/background).
**Use it when:** containerized apps, microservices, workers that scale on events.
**Sahl:** the other candidate — strong fit for "same image, two surfaces + workers + jobs."

### Azure Kubernetes Service (AKS) — ❌ Not used (📄 Phase-3 evolution)
**What it is:** managed Kubernetes. Maximum flexibility; you operate a cluster.
**Why not:** too much ops for one developer and credits. Documented as what a large national platform with many vendor teams might use.

### Azure Functions — ✅ Used
**What it is:** small pieces of code triggered by events (HTTP call, queue message, timer, blob upload). Serverless: pay per execution on consumption plans.
**Sahl:** PDF generation + signing, payment webhook intake, maybe workflow orchestration (Durable Functions, decision O3), AI ingestion.

### Azure Container Instances (ACI) — ❌ Not used
**What it is:** run a single container quickly, no orchestration.
**Why not:** Container Apps does this better for our needs.

### Azure Static Web Apps — ❌ Not used (was candidate before the Inertia decision)
**What it is:** hosting for static frontends (React/Vue builds) with global distribution and built-in auth.
**Why not:** with Inertia, Laravel serves the frontend.

### Azure Batch — ❌ Not used
**What it is:** large-scale parallel compute jobs (rendering, simulations).
**Why not:** no such workloads.

### Azure Spring Apps / Service Fabric / Cloud Services — ❌ Not used
**Why not:** Java-specific / legacy microservice platforms / retired. (Spring Apps has a retirement announcement ⚠ Verify.)

### Azure Virtual Desktop — ❌ Not used
**What it is:** Windows desktops streamed from Azure.
**Why not:** not part of a citizen portal. (Governments do use it for secure staff access.)

---

## 4. Containers

### Azure Container Registry (ACR) — ✅ Used
**What it is:** a private Docker Hub inside Azure. Stores your container images; can scan them for vulnerabilities; can be made private (Premium SKU for private endpoints).
**Sahl:** CI builds the Laravel image and pushes it here; Container Apps/App Service pull from it using managed identity.

---

## 5. Web, edge & delivery — "how traffic gets in"

### Azure DNS — ✅ Used (if we use a domain) / Private DNS ✅ Used
**What it is:** **Public DNS zones** host your domain's records (`sahl.example`). **Private DNS zones** resolve names *inside* your VNets — essential for private endpoints.
**Sahl:** private DNS zones definitely; public DNS if you buy a cheap domain for the portfolio.

### Azure Front Door — ✅ Used (Standard) / 🏛 Premium in gov profile
**What it is:** Microsoft's global entry point: users connect to the nearest Microsoft edge location; Front Door terminates TLS, caches static content (CDN), applies **WAF**, and routes to your app. **Premium** can reach your app over **Private Link** (so the app has no public endpoint).
**Sahl:** the citizen portal's front door.

### Web Application Firewall (WAF) — ✅ Used (basic) / 🏛 full in gov profile
**What it is:** inspects HTTP requests and blocks attacks (SQL injection, XSS, bad bots) using managed rule sets. Runs **on** Front Door or Application Gateway.
**Sahl:** on Front Door.

### Application Gateway — ❓ Open decision O4 (likely ❌)
**What it is:** a *regional* layer-7 load balancer with WAF, deployed **inside** your VNet.
**Use it when:** you need an internal L7 entry point, or traffic must stay regional.
**Sahl:** alternative to Front Door; possibly for the internal back-office entry in the gov profile.

### Azure Load Balancer — ❌ Not used
**What it is:** layer-4 (TCP/UDP) load balancing, mainly for VMs.
**Why not:** our PaaS services balance internally.

### Traffic Manager — ❌ Not used
**What it is:** DNS-based routing between regions (failover, geographic routing).
**Why not:** single region (residency).

### Azure CDN — ❌ Not used separately
**Why not:** CDN capabilities are now part of Front Door.

### DDoS Protection (Network Protection) — 📄 Documented
**What it is:** advanced protection against volumetric attacks on public IPs in a VNet, with telemetry and cost protection. Basic infrastructure protection is free and automatic.
**Why only documented:** very expensive monthly fee; Front Door already absorbs a lot at the edge. Production would evaluate it.

---

## 6. Networking — "the private roads"

### Virtual Network (VNet) & subnets — ✅ Used
**What it is:** your private network in Azure, split into subnets.
**Sahl:** spoke VNet with subnets for apps, functions, private endpoints, Postgres.

### Network Security Groups (NSG) — ✅ Used
**What it is:** allow/deny rules on subnets (who can talk to whom, on which ports).

### Private Endpoints / Private Link — ✅ Used
**What it is:** gives a PaaS service (Key Vault, Storage, Postgres…) a private IP *inside* your VNet, so you can switch off its public access entirely.
**Sahl:** core of the "no public data services" principle. Note: some services need a higher SKU for private endpoints.

### Service Endpoints — ❌ Not used (📄 compared)
**What it is:** an older, cheaper way to restrict a PaaS service to your subnet — but the service keeps a public IP.
**Why not:** private endpoints are the government standard; we explain the difference.

### VNet Peering — 🏛 Gov profile
**What it is:** connects two VNets privately.
**Sahl:** hub ↔ spokes in the gov profile.

### Azure Firewall — 🏛 Gov profile
**What it is:** a managed central firewall: controls **outbound** traffic (only allow the national IdP, payment gateway…), inspects traffic between networks, threat-intel filtering. Tiers Basic/Standard/Premium.
**Sahl:** hub firewall during bursts — billed per hour, expensive if left on.

### Azure Bastion — 🏛 Gov profile
**What it is:** browser-based RDP/SSH to private machines — no public IPs on VMs.
**Sahl:** the "admin access path" into the private network during bursts (e.g., a tiny jumpbox to reach the internal back-office).

### NAT Gateway — ❓ Maybe
**What it is:** gives a subnet a fixed outbound public IP for internet calls (useful when a partner allow-lists your IP).
**Sahl:** maybe, if the mock payment gateway "requires" IP allow-listing — a realistic scenario.

### User-Defined Routes (route tables) — 🏛 Gov profile
**What it is:** custom routing — e.g., "send all internet-bound traffic through the firewall."

### DNS Private Resolver — 🏛 Gov profile
**What it is:** managed DNS forwarding between Azure and on-prem networks.
**Sahl:** in the hub, to model hybrid resolution.

### VPN Gateway — ❓ Maybe (gov profile)
**What it is:** encrypted tunnel between Azure and another network (office, home, on-prem). *Point-to-site* = your laptop connects to the VNet.
**Sahl:** possible way for "employees on the government network" to reach the internal back-office in a burst. Hourly cost.

### ExpressRoute — 📄 Documented
**What it is:** a private, dedicated circuit from a datacenter to Azure through a telecom provider — not over the internet.
**Why only documented:** requires a physical provider contract. Real governments connect their datacenters this way.

### Virtual WAN — ❌ Not used
**What it is:** Microsoft-managed hub-and-spoke for large, multi-region networks.
**Why not:** overkill for one workload.

### Network Watcher — ✅ Used (troubleshooting)
**What it is:** tools to diagnose connectivity (is traffic allowed? which route?), flow logs.
**Sahl:** for debugging private networking.

---

## 7. Databases

### Azure Database for PostgreSQL – Flexible Server — ✅ Used
**What it is:** managed Postgres with backups, HA, private networking, and Entra authentication.
**Sahl:** the main database (Burstable tier in dev).

### Azure SQL Database — ❌ Not used (📄 strong alternative)
**What it is:** managed SQL Server engine. Serverless tier with auto-pause; **Ledger** tables (tamper-evident); excellent tooling.
**Why not:** chose Postgres for Laravel familiarity and features (JSONB, RLS). Very common in Microsoft-centric governments — know it.

### Azure SQL Managed Instance / SQL Server on VMs — ❌ Not used
**What they are:** near-100% SQL Server compatibility for migrating legacy apps / full control.
**Why not:** no legacy SQL Server to migrate.

### Azure Database for MySQL – Flexible Server — ❌ Not used
**Why not:** Postgres chosen.

### Azure Cosmos DB — ❌ Not used (📄 compared)
**What it is:** globally distributed NoSQL database (document, key-value, graph), single-digit-ms latency, multi-region writes.
**Why not:** our data is relational with strong consistency needs.

### Azure Managed Redis / Azure Cache for Redis — ❓ Open decision O8
**What it is:** managed in-memory cache (sessions, cache, locks, rate-limit counters). Microsoft is moving from "Azure Cache for Redis" to **Azure Managed Redis** ⚠ Verify current offerings.
**Sahl:** needed for Laravel sessions when scaled out (Inertia BFF) — managed service vs a Redis container is the decision.

---

## 8. Storage

### Storage account → Blob Storage — ✅ Used
**What it is:** object storage for files ("blobs"), organized in containers. Tiers Hot/Cool/Cold/Archive; versioning; soft delete; **immutability (WORM)** policies.
**Sahl:** uploaded documents, issued PDFs, and the **immutable audit trail**.

### Storage account → Queues — ❌ Not used
**What it is:** very simple message queues.
**Why not:** Service Bus or Event Grid chosen for richer messaging (decision O2).

### Storage account → Tables — ❌ Not used
**What it is:** simple key-value NoSQL storage.
**Why not:** no need.

### Azure Files — ❌ Not used
**What it is:** managed network file shares (SMB/NFS) — "a shared drive".
**Why not:** stateless app; files go to Blob.

### Managed Disks — ❌ Not used
**What it is:** virtual hard disks for VMs.

### Data Lake Storage Gen2 — ❌ Not used
**What it is:** Blob storage optimized for big-data analytics (hierarchical namespace).
**Why not:** no analytics platform in scope.

### Azure Backup — 📄 Documented
**What it is:** central backup service for VMs, databases, file shares, blobs.
**Sahl:** Postgres and Blob have built-in backup/versioning; Azure Backup documented for production policy.

### Azure Site Recovery — ❌ Not used
**What it is:** replicates VMs to another region for disaster recovery.
**Why not:** no VMs, and cross-border DR conflicts with residency.

---

## 9. Integration — "how parts talk"

### Azure API Management (APIM) — ✅ Used
**What it is:** an API gateway + developer portal: authenticates callers, applies rate limits/quotas, transforms requests, versions APIs, publishes documentation, manages "subscriptions" per consumer.
**Sahl:** the **national integration platform** model — inter-ministry APIs (Commerce registry, National Registry), payment webhooks. Developer tier in dev; v2 tiers / Premium for networking in the gov profile ⚠ Verify tier features.

### Azure Service Bus — ❓ Open decision O2
**What it is:** enterprise message broker: queues + topics, guaranteed delivery, dead-letter queues, duplicate detection, sessions (ordering), transactions.
**Sahl:** strong candidate for business commands/events (`ApplicationSubmitted`…).

### Azure Event Grid — ❓ Open decision O2
**What it is:** lightweight event routing ("something happened" → push to subscribers). Many Azure services emit events natively (e.g., "blob created").
**Sahl:** candidate for reacting to uploads and fan-out notifications.

### Azure Event Hubs — ❌ Not used
**What it is:** huge-volume event streaming (telemetry, clickstreams), Kafka-compatible.
**Why not:** we have business events, not streams.

### Azure Logic Apps — ❓ Open decision O3
**What it is:** low-code workflows built visually, with hundreds of connectors (email, SharePoint, SAP, SQL…).
**Sahl:** candidate engine for the permit approval workflow.

### Durable Functions — ❓ Open decision O3
**What it is:** an extension of Azure Functions for long-running, stateful workflows written in code (timers, waiting for human approval, fan-out/fan-in).
**Sahl:** the other workflow candidate.

### Azure API Center — ❌ Not used (📄 mentioned)
**What it is:** an inventory/catalog of all APIs in an organization (across gateways).
**Why not:** small scale; good to mention for a national API catalog.

### Azure Relay — ❌ Not used
**What it is:** expose on-prem services to the cloud without opening firewall ports.
**Why not:** no on-prem.

### Azure Data Factory — ❌ Not used
**What it is:** ETL/data pipelines — move and transform data between systems.
**Why not:** no data-warehouse scope.

---

## 10. Security

### Azure Key Vault — ✅ Used
**What it is:** secure storage for **secrets** (passwords, connection strings), **keys** (for signing/encryption — operations happen *inside* the vault), and **certificates** (TLS). Standard (software) or Premium (HSM-backed keys).
**Sahl:** document-signing keys per ministry; any unavoidable secrets; TLS certificates. Premium in gov profile.

### Azure Key Vault Managed HSM — 📄 Documented
**What it is:** dedicated, single-tenant hardware security modules (FIPS 140-3 Level 3) that you fully control.
**Why only documented:** expensive; production choice for national-grade signing keys.

### Azure Dedicated HSM / Payment HSM — ❌ Not used
**What they are:** physical HSMs leased to you / HSMs for payment-industry operations.
**Why not:** far beyond scope.

### Microsoft Defender for Cloud — 📄 Documented (free tier ✅ looked at)
**What it is:** security posture management (secure score, recommendations, regulatory compliance dashboards) + paid workload protection plans (servers, storage malware scanning, databases, containers, APIs, Key Vault).
**Sahl:** free posture view used for the Well-Architected/security review; paid plans documented (and maybe one short trial, e.g., storage malware scanning for uploads).

### Microsoft Sentinel — 📄 Documented
**What it is:** cloud SIEM/SOAR — collects security logs, detects threats with analytics rules, automates responses.
**Why only documented:** budget (you deselected it). Production governments feed a SOC with it or similar.

### Azure Confidential Computing — 📄 Mentioned
**What it is:** VMs/containers that keep data encrypted **even while being processed** (hardware enclaves), so even the cloud operator can't see it.
**Why only mentioned:** relevant to **sovereignty** discussions; out of PoC scope.

### Microsoft Purview — 📄 Mentioned
**What it is:** data governance and compliance: data catalog, **classification/labeling of sensitive data**, lineage, data-loss prevention.
**Why only mentioned:** very relevant for government data classification; heavy to set up.

---

## 11. Monitoring & operations — "seeing what's happening"

### Azure Monitor — ✅ Used
**What it is:** the umbrella for metrics, logs, alerts, and dashboards across Azure.

### Log Analytics workspace — ✅ Used
**What it is:** the central log database; query with **KQL**. Resources send logs here via **diagnostic settings**.
**Sahl:** one workspace for everything (watch ingestion cost!).

### Application Insights — ✅ Used
**What it is:** application performance monitoring: requests, errors, dependencies (DB/HTTP calls), traces across services, availability tests.
**Sahl:** for both Laravel surfaces, Functions, and the mocks.

### Alerts & Action Groups — ✅ Used
**What they are:** conditions (error rate > 5%, DLQ not empty) → actions (email, SMS, webhook).

### Workbooks / Dashboards — ✅ Used
**What they are:** interactive reports built on logs and metrics.
**Sahl:** an operations dashboard and an SLA dashboard (permit reviews overdue).

### Azure Managed Grafana / Managed Prometheus — ❌ Not used
**What they are:** managed open-source dashboards/metrics (common with Kubernetes).
**Why not:** Workbooks are enough without AKS.

### Azure Load Testing — ❓ Maybe
**What it is:** run load tests (JMeter/Locust) at scale.
**Sahl:** maybe one small test to show autoscaling — nice demo, low cost.

### Azure Chaos Studio — ❌ Not used (📄 mentioned)
**What it is:** inject failures into Azure resources to test resilience.
**Why not:** our mock payment gateway's chaos mode covers the learning cheaply.

---

## 12. AI

### Azure OpenAI Service (within Azure AI Foundry) — ✅ Used
**What it is:** OpenAI models (GPT family, embeddings) hosted by Microsoft under Azure's security and compliance terms. **Azure AI Foundry** is the umbrella portal/platform for building AI apps (models, evaluation, safety) — being rebranded as **Microsoft Foundry** ⚠ Verify.
**Sahl:** embeddings + chat model for the RAG assistant. Check model availability per region.

### Azure AI Search — ✅ Used
**What it is:** search engine as a service: keyword, vector, hybrid search, semantic ranking, Arabic analyzers.
**Sahl:** the RAG index over service guides (free/basic tier).

### Azure AI Content Safety — ✅ Used
**What it is:** detects harmful content and prompt-injection attempts.
**Sahl:** filters assistant input/output.

### Azure AI Document Intelligence — ❌ Not used (📄 Phase 2 idea)
**What it is:** extracts fields and tables from documents (IDs, invoices, forms).
**Why not:** you didn't select it; documented as a future "auto-validate uploads" feature.

### Azure AI Translator / Language / Speech / Vision — ❌ Not used
**What they are:** translation, text analysis (entities, sentiment, PII detection), speech-to-text, image analysis.
**Why not:** not needed — though **Language PII detection** is a nice idea for masking logs.

### Azure Machine Learning — ❌ Not used
**What it is:** platform to train and deploy your own ML models.
**Why not:** we use pre-built models.

### Azure Bot Service — ❌ Not used
**What it is:** framework/hosting for chatbots across channels (Teams, web).
**Why not:** our assistant is a simple web chat on our own API.

---

## 13. Communication & messaging to users

### Azure Communication Services (ACS) — ✅ Used (email)
**What it is:** APIs for email, SMS, voice, video, chat.
**Sahl:** bilingual **email** for appointment and certificate notifications (decision D29). SMS not used (cost/registration rules).

### Azure Notification Hubs — ❌ Not used
**What it is:** push notifications to mobile apps.
**Why not:** no mobile app.

### Azure SignalR / Web PubSub — ❌ Not used
**What they are:** real-time messaging to browsers (live updates).
**Why not:** polling is fine for a PoC; could be used for live inbox updates later.

---

## 14. DevOps & developer tools

### GitHub + GitHub Actions — ✅ Used (outside Azure)
**What it is:** source control + CI/CD, connected to Azure through OIDC federation.

### Azure DevOps — ❌ Not used (📄 compared)
**What it is:** Microsoft's older all-in-one: Boards, Repos, Pipelines, Artifacts, Test Plans.
**Why not:** chose GitHub; but very common in GCC government — know that it exists and is equivalent.

### Azure Developer CLI (`azd`) — ❌ Not used
**What it is:** opinionated CLI that scaffolds + provisions + deploys app templates end-to-end.
**Why not:** it hides the steps we want to learn. Great later for speed.

### Azure Deployment Environments / Microsoft Dev Box — ❌ Not used
**What they are:** self-service environments from templates / cloud developer workstations.
**Why not:** enterprise developer-platform tooling; out of scope.

---

## 15. Data & analytics — all ❌ Not used

| Service | What it is | Why not in Sahl |
|---|---|---|
| **Microsoft Fabric** | All-in-one analytics SaaS (lakehouse, warehouse, Power BI) | No analytics scope (a government "service performance dashboard" could use it later) |
| **Azure Synapse Analytics** | Data warehouse + big data analytics | Same; Fabric is the newer direction |
| **Azure Databricks** | Spark-based data engineering/ML | Same |
| **Azure Data Explorer** | Fast analytics over logs/telemetry (KQL) | Log Analytics covers our needs |
| **Azure Stream Analytics** | Real-time queries over streams | No streams |
| **Power BI** (not strictly Azure) | Business dashboards | Could visualize SLA/KPI data — optional portfolio extra |

---

## 16. Hybrid, sovereign & migration — 📄 Know they exist

| Service | What it is | Why it matters for GCC government |
|---|---|---|
| **Azure Local** (formerly Azure Stack HCI) | Run Azure services on hardware in *your own* datacenter | Data that must never leave government premises |
| **Sovereign cloud offerings** | Microsoft cloud variants with in-country control, sometimes operated with local partners | National data-sovereignty requirements ⚠ Verify current GCC offerings |
| **Azure Migrate** | Discover and migrate on-prem servers/DBs to Azure | Ministries moving legacy systems |
| **Azure Database Migration Service** | Move databases into Azure with minimal downtime | Same |

---

## 17. Everything else — ❌ Not relevant to Sahl

IoT (IoT Hub, IoT Operations, Digital Twins), Mixed Reality, Quantum, Media services (retired), Blockchain (retired), Azure VMware Solution, HPC, Orbital/Space, Gaming (PlayFab). Good to know they exist; not used.

---

## Summary: Sahl's Azure bill of materials

### Everyday (lean profile)
| Layer | Services |
|---|---|
| Governance | Management group, subscription, resource groups, **Azure Policy**, **Budgets**, tags |
| Identity | **Entra ID** (workforce), **Entra External ID** (citizens), **managed identities**, GitHub **workload identity federation** |
| Edge | **Front Door Standard + WAF**, Azure DNS (optional) |
| Compute | **Container Apps or App Service** (O1), **Azure Functions** |
| Containers | **Container Registry** |
| Integration | **API Management**, **Service Bus and/or Event Grid** (O2), Durable Functions or Logic Apps (O3) |
| Data | **PostgreSQL Flexible Server**, **Blob Storage** (incl. immutable audit), Redis (O8) |
| Network | **VNet**, subnets, **NSGs**, **private endpoints**, **private DNS zones** |
| Security | **Key Vault** |
| AI | **Azure OpenAI/AI Foundry**, **AI Search**, **Content Safety** |
| Communication | **Communication Services (email)** |
| Operations | **Azure Monitor**, **Log Analytics**, **Application Insights**, alerts, workbooks, Advisor, Service Health |

### Gov profile (bursts)
Hub VNet + **peering**, **Azure Firewall**, **Bastion**, **DNS Private Resolver**, **Front Door Premium + Private Link**, **UDRs**, **Key Vault Premium (HSM keys)**, zone-redundant SKUs, possibly **VPN Gateway** / **Application Gateway**, resource locks.

### Documented only (production recommendations)
Separate subscriptions per environment, PIM, Conditional Access (if no license), Managed HSM, Defender plans, Sentinel, DDoS Network Protection, ExpressRoute, Azure Backup policies, Purview, Arc, Azure Local / sovereign cloud, AKS (scale-out evolution), Entra Verified ID.

---

## Self-test

1. Which three services together make "no public endpoints except the front door" possible?
2. What's the difference between Front Door and Application Gateway?
3. Why does Sahl use managed identities instead of storing passwords in Key Vault?
4. Name two services we documented but didn't deploy, and the reason for each.
5. Service Bus vs Event Grid vs Event Hubs — one sentence each.
6. Which services would a GCC government use to keep some systems on-premises but govern them like Azure?
