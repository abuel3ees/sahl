# Sahl (سهل) — The Architecture Learning Guide

> A deep-dive study guide for designing a GCC-style government e-services platform on Azure.
> Every decision is explained four ways: **what we chose**, **what we didn't choose**, **what real governments do**, and **how to make the call yourself**.

---

## Read this first

### What Sahl is

Sahl is a **fictional** unified government e-services platform, modeled on how Gulf (GCC) governments build citizen portals. It is a **career portfolio project**: the goal is not only to build it, but to be able to *defend every decision in an architect interview*.

- Scenario country: **Qatar** (modeled, not affiliated).
- Deployed for the PoC to: **Azure Germany West Central (Frankfurt)**.
- Everything is fictional: the ministries, the national ID provider, the payment gateway, the citizens. No real government names, logos, or systems are used.

### A note on accuracy

Laws, regulations, and cloud availability change. Where this guide describes a real government framework (Qatar's NIA Policy, PDPPL, Saudi NCA controls, etc.) it gives a **simplified, educational summary**, not legal advice. Before you quote a specific article, control number, or region availability in an interview or document, check the current official source. That habit — "verify the constraint before you design on it" — is itself an architect skill.

Things flagged with **⚠ Verify** are facts that move fast (region launches, product renames, prices).

### Companion documents

| Document | Use it for |
|---|---|
| `azure-services-catalog.md` | What every major Azure service is, and whether Sahl uses it (and why/why not) |
| `azure-setup-playbook.md` | The step-by-step order for setting up a web system on Azure, from empty account to go-live |
| `bicep-guide.md` | Learning to write Bicep (Infrastructure as Code), lesson by lesson |
| `../architecture/erd.md` | Sahl's data model |

### How each chapter is structured

1. **The simple version** — an analogy you could explain to a non-technical manager.
2. **The concepts** — the terminology, properly.
3. **What Sahl does** — the decision and the reason.
4. **What we didn't choose** — the alternatives and why they lost *for us*.
5. **What GCC governments actually do** — real-world patterns.
6. **How to decide yourself** — the questions an architect asks.
7. **Self-test** — questions you should be able to answer out loud.

### Table of contents

A. [Azure from absolute zero](#a-azure-from-absolute-zero) ← **start here if you are new to Azure**
0. [How architects think](#0-how-architects-think)
1. [What a GCC government platform actually is](#1-what-a-gcc-government-platform-actually-is)
2. [Region, data residency and sovereignty](#2-region-data-residency-and-sovereignty)
3. [Architecture style: monolith, modular monolith, microservices](#3-architecture-style)
4. [Compute: where the code runs (open decision)](#4-compute-where-the-code-runs)
5. [Frontend: Inertia as BFF, Arabic, RTL, accessibility](#5-frontend)
6. [Identity fundamentals: OAuth 2.0, OIDC, tokens](#6-identity-fundamentals)
7. [National identity, federation and Entra](#7-national-identity-federation-and-entra)
8. [Authorization: roles, scopes, delegation](#8-authorization)
9. [Multi-tenancy: one platform, many ministries](#9-multi-tenancy)
10. [Data: database, encryption, citizen data](#10-data)
11. [Integration: APIs, messaging, events](#11-integration)
12. [Payments: webhooks, idempotency, reconciliation](#12-payments)
13. [Workflows: the permit approval engine](#13-workflows)
14. [Documents and trust: signatures, PKI, QR verification](#14-documents-and-trust)
15. [Networking: from zero to government-grade](#15-networking)
16. [The AI assistant: RAG](#16-the-ai-assistant)
17. [Audit, logging and observability](#17-audit-logging-and-observability)
18. [Security and compliance](#18-security-and-compliance)
19. [Reliability: SLAs, RPO/RTO, disaster recovery](#19-reliability)
20. [DevOps, IaC and environments](#20-devops-iac-and-environments)
21. [Cost management](#21-cost-management)
22. [The decision register](#22-the-decision-register)
23. [Glossary](#23-glossary)
24. [Learning path](#24-learning-path)

---

## A. Azure from absolute zero

> **Start here if you've never used Azure.** Every later chapter assumes you understand this one. We'll do all of this hands-on together in **Step 0**, so reading it first means the clicks will make sense.

### The simple version

Azure is Microsoft's giant collection of computers, spread across datacenters around the world, that you **rent by the minute**. Instead of buying a server, you ask Azure for "a Postgres database" or "a place to run my Laravel container," and it appears in a few minutes. You pay for what you use, and you can delete it just as fast.

As a Laravel dev you've probably used Forge, a VPS, or shared hosting. Azure is that idea taken much further: you don't just rent the server, you also rent the database, the queue, the file storage, the login system, the firewall and the AI model, each as a separate managed **service**.

### A.1 The hierarchy: how Azure is organized

This is the single most important mental model. Everything in Azure sits inside this tree:

```
Microsoft Entra tenant           ← "your organization's identity directory" (users, groups, apps)
 └── Management groups           ← folders for organizing subscriptions (optional at first)
      └── Subscription           ← the billing + access boundary ("the bank account")
           └── Resource group    ← a folder of related resources ("the project folder")
                └── Resource     ← the actual thing: a database, a VNet, a key vault…
```

**Analogy, a government ministry:**
- **Tenant** = the ministry itself, with its staff directory.
- **Management group** = departments ("Production systems", "Sandbox").
- **Subscription** = a budget line with its own bill. Costs are tracked and limited here.
- **Resource group** = a project folder. Everything for "Sahl dev" goes in one folder, and you can delete the whole folder in one action.
- **Resource** = an individual item in the folder.

**Things that confuse every beginner:**

| Term | What it really is |
|---|---|
| **Microsoft account / work account** | *You*, the person logging in (e.g., your email) |
| **Tenant (Entra ID directory)** | The directory your account lives in. When you sign up for Azure, a tenant is created for you automatically |
| **Subscription** | A billing container *inside* a tenant. One tenant can have many subscriptions |
| **Resource group** | A logical folder *inside* a subscription. It has a region, but only for its metadata. Its resources can live in other regions |

**Rules of resource groups:**
- Every resource lives in **exactly one** resource group.
- Group things that share a **lifecycle**, meaning they're created and deleted together (e.g., `rg-sahl-dev-gwc`).
- Deleting a resource group **deletes everything inside it**. That's dangerous in production and very handy for us when tearing down the gov-profile bursts.

### A.2 Resources, resource providers, and Azure Resource Manager

- **Resource** — any manageable item. Each has a **resource ID** like:
  `/subscriptions/<sub-id>/resourceGroups/rg-sahl-dev-gwc/providers/Microsoft.KeyVault/vaults/kv-sahl-dev-gwc`
  Read it like a file path. You'll see these IDs everywhere, in Bicep, the CLI and RBAC.
- **Resource provider** — the Azure service "namespace" that offers a type of resource: `Microsoft.Web` (App Service), `Microsoft.App` (Container Apps), `Microsoft.DBforPostgreSQL`, `Microsoft.KeyVault`, `Microsoft.Network`. In a new subscription, some providers must be **registered** before first use (the portal usually does it for you; the CLI sometimes tells you to).
- **Azure Resource Manager (ARM)** — the one front door for **managing** Azure. Whether you click in the portal, run the CLI, or deploy Bicep, it all becomes a request to ARM, which checks your permissions and policies and then creates or changes the resource.

### A.3 Control plane vs data plane (very important)

There are two different "levels" of access to every resource:

| | **Control plane** (management) | **Data plane** (contents) |
|---|---|---|
| Question | "Can you create, configure or delete this Key Vault?" | "Can you read the secrets *inside* it?" |
| Goes through | ARM (`management.azure.com`) | The service's own endpoint (`kv-sahl.vault.azure.net`) |
| Example roles | Owner, Contributor, Reader | Key Vault Secrets User, Storage Blob Data Reader |

**Why it matters:** being **Owner** of a subscription does **not** automatically let you read blobs or Key Vault secrets when data-plane RBAC is used. Beginners hit this wall constantly ("I'm Owner, why do I get 403?"). It's deliberate. Government security teams love it because the people who *manage* infrastructure don't automatically see *citizen data*.

### A.4 Azure RBAC: who can do what

**Azure RBAC** controls access to Azure resources. Every **role assignment** has three parts:

```
WHO (security principal)  +  WHAT (role definition)  +  WHERE (scope)
   a user / group /            Contributor              /subscriptions/…/resourceGroups/rg-sahl-dev-gwc
   service principal /
   managed identity
```

- **Security principal** — a user, a group, a **service principal** (an app's identity), or a **managed identity** (an Azure resource's identity).
- **Role definition** — a named set of permissions. Built-in examples:
  - **Owner** — everything, including granting access to others.
  - **Contributor** — create/change/delete resources, but can't grant access.
  - **Reader** — view only.
  - Plus hundreds of specific roles (e.g., *Key Vault Crypto User*, *Storage Blob Data Contributor*).
- **Scope** — management group → subscription → resource group → single resource. Permissions **inherit downward**: Reader on a subscription = Reader on every resource group inside it.

**Least privilege in practice:** give the narrowest role at the smallest scope. Sahl's API identity gets *Key Vault Crypto User* on **one key vault**, not Contributor on the subscription.

**⚠ Three different things are all called "roles":**

| Kind | Controls | Example |
|---|---|---|
| **Azure RBAC roles** | Access to Azure *resources* | "Contributor on rg-sahl-dev" |
| **Entra directory roles** | Administering the *directory* itself | "Global Administrator", "Application Administrator" |
| **App roles** | Permissions inside *your application* | Sahl's "Reviewer", "Approver" (Chapter 8) |

Keep these separate in your head. Interviewers test this.

### A.5 Ways to work with Azure

| Tool | What it is | When we use it |
|---|---|---|
| **Azure portal** (portal.azure.com) | The website | Exploring, learning, checking what Bicep created, viewing costs and logs. **Never** our way to create things permanently |
| **Azure CLI** (`az`) | Command-line tool | Logging in, quick queries, running Bicep deployments, scripting |
| **Azure PowerShell** | Same, for PowerShell | Not used (CLI is more common on macOS/Linux) |
| **Cloud Shell** | A browser terminal with the CLI preinstalled | When you're away from your machine |
| **Bicep** | Infrastructure as Code (Chapter 20) | Creating **everything** for real |
| **REST API** | What all the above call under the hood | Rarely directly |

**Our rule:** *look* in the portal, *build* with Bicep, *glue* with the CLI. If something exists that isn't in Bicep, it doesn't count.

A useful learning trick: when you create something in the portal, the last screen has **"Download a template for automation"**. It shows you the ARM template for what you clicked, a good bridge to Bicep.

### A.6 Regions, SKUs, quotas

- **Region** — where a resource physically runs (Chapter 2). Most resources ask for a `location`. Ours is `germanywestcentral`.
- **SKU / tier / plan** — the size and feature level of a service (e.g., Postgres *Burstable B1ms* vs *General Purpose D4ds_v5*; Key Vault *Standard* vs *Premium*). **SKUs decide both price and features.** Often a feature you need (private endpoints, zone redundancy) only exists on a higher SKU. Much of an architect's job is SKU selection.
- **Quotas / limits** — per-subscription caps (e.g., number of vCPUs per region). Free and trial subscriptions have **low quotas** and some services or SKUs **aren't available at all**. If a deployment fails with "quota exceeded," that's what it is. Some quotas can be raised with a support request; some can't on trial subscriptions.

### A.7 How Azure charges you

- Every resource has **meters** (per hour, per GB stored, per million operations, per GB transferred out).
- Some things cost money **even when idle** because they're reserved for you (Firewall, App Gateway, VPN Gateway, dedicated App Service plans, Premium Service Bus, Postgres compute while running).
- Other things cost almost nothing when idle (Functions consumption, Container Apps scaled to zero, Storage with little data).
- **Stopping vs deleting:** a stopped VM still pays for its disk; a stopped Postgres server still pays for storage; most PaaS services can't be "stopped" at all. **Deleting the resource group** is the only true zero.
- **Tools:** Pricing Calculator (estimate before), Cost Management + Budgets (watch during), Cost analysis (understand after).

**Free / credit accounts (⚠ Verify current terms; Microsoft changes them):**
- The **Azure free account** has historically given **a one-time credit (around $200) that expires after 30 days**, plus some services free for 12 months and some always free.
- **Visual Studio / MSDN subscriptions** give a **monthly** credit that renews.
- **Azure for Students** gives credit without a card (for eligible students).
- Trial subscriptions often have a **spending limit**: when credit runs out, resources are **disabled** rather than billed. That's safe, but it can stop your environment mid-demo.

> **This matters for our plan:** if your credit expires after 30 days, we must sequence the work so the Azure-heavy steps happen inside that window, or move to a pay-as-you-go subscription with a strict budget. We'll decide this in Step 0.

### A.8 Tags and locks

- **Tags** — key/value labels on resources (`environment=dev`, `workload=sahl`, `owner=you`, `data-classification=restricted`). Used for cost reports, policy, and automation.
- **Resource locks** — `CanNotDelete` or `ReadOnly` on a resource/RG/subscription to prevent accidents. Even Owners must remove the lock first. Production governments lock critical resources such as audit storage and key vaults.

### A.9 Map of Azure service categories (and what Sahl uses)

| Category | Services you'll meet in Sahl |
|---|---|
| **Identity** | Microsoft Entra ID, Entra External ID, managed identities |
| **Compute** | Container Apps *or* App Service, Azure Functions |
| **Containers** | Azure Container Registry |
| **Web / edge** | Static Web Apps, Front Door, Application Gateway |
| **Networking** | Virtual Network, NSGs, Private Endpoints, Private DNS, Azure Firewall, Bastion |
| **Databases** | Azure Database for PostgreSQL Flexible Server, Redis |
| **Storage** | Storage accounts (Blob), immutability policies |
| **Integration** | API Management, Service Bus / Event Grid, Logic Apps (maybe) |
| **Security** | Key Vault, Azure Policy, (Defender for Cloud) |
| **Monitoring** | Azure Monitor, Log Analytics, Application Insights |
| **AI** | Azure OpenAI / AI Foundry, Azure AI Search, Content Safety |
| **Communication** | Azure Communication Services (email) |
| **DevOps** | GitHub Actions + OIDC (outside Azure, connected to it) |

You don't need to know them all now. Each one is introduced in its step.

### A.10 Where to learn and look things up

- **Microsoft Learn** (learn.microsoft.com): free training paths. **AZ-900 (Azure Fundamentals)** learning path covers this chapter in more depth; worth doing alongside Step 0.
- **Azure documentation**: each service has "Overview", "Concepts", "Quickstart", "How-to", and "Pricing" pages. Read **Overview + Concepts** first.
- **Azure Architecture Center**: reference architectures and design patterns, which is where architects read.
- **Products available by region** page: check before choosing a region.
- **Azure Updates**: service announcements and retirements.

### A.11 Safety rules for your first weeks

1. **Turn on MFA** on your Azure account on day one.
2. **Create a budget with alerts** before creating anything else.
3. **Use one resource group per environment/burst** so cleanup is a single delete.
4. **Never commit secrets** (keys, connection strings) to Git. We'll aim for zero secrets anyway.
5. **Check the region and SKU** on every resource. A wrong region or a Premium SKU is the most common surprise bill.
6. **Delete what you're not using.** Check Cost Management weekly.
7. When something fails, **read the full error**. Azure errors are verbose but usually say exactly what's wrong (quota, SKU not available in region, provider not registered, missing permission).

### What Step 0 will look like (hands-on)

1. Create/confirm your Azure account and turn on MFA.
2. Tour the portal: tenant, subscription, Cost Management, resource groups.
3. Install the **Azure CLI** and **Bicep** on your Mac; `az login`; `az account show`.
4. Create a **budget with alerts**.
5. Create a resource group in the CLI, look at it in the portal, put a tag on it, delete it.
6. Create one tiny resource (a Storage account), find its resource ID, look at its control-plane vs data-plane access, then delete it.
7. Write your first mini-ADR: "Which subscription type are we using and what does that mean for the plan?"

### Self-test

- Draw the tenant → management group → subscription → resource group → resource tree and explain each level.
- What happens when you delete a resource group?
- You're Owner of the subscription but get "403 Forbidden" reading a blob. Why?
- What are the three parts of a role assignment?
- Name the three different kinds of "roles" and what each controls.
- What's a SKU and why does it matter for features, not just price?
- Why do we create things with Bicep instead of the portal?
- What's the difference between stopping and deleting in terms of cost?

---

## 0. How architects think

### The simple version

A developer asks: *"How do I build this?"*
An architect asks: *"What must be true about this system, what are we willing to give up, and what will it cost us later?"*

Architecture is the set of decisions that are **expensive to change later**. Choosing a button color is not architecture. Choosing whether citizen data can leave the country is.

### The concepts

**Functional requirements** — what the system does. *"A citizen can apply for a building permit."*

**Non-functional requirements (NFRs)**, also called **quality attributes** — how well it does it:

| Quality attribute | The question it answers | Sahl example |
|---|---|---|
| Security | Who can do what, and how do we stop everyone else? | Only Municipality reviewers see permit files |
| Availability | How much downtime is acceptable? | Portal may be down a few hours/month (PoC) |
| Reliability | Does it do the right thing even when parts fail? | A payment is never charged twice |
| Scalability | What happens when load grows 10×? | Month-start visa season peaks |
| Performance | How fast? | Pages under 2 s |
| Maintainability | How easy is it to change? | Adding a 4th ministry should be config, not code |
| Auditability | Can we prove what happened? | Every employee action logged immutably |
| Compliance | Does it obey the law and policy? | Citizen data residency, privacy law |
| Cost | What does it cost to run and to change? | Must fit in free credits |
| Usability / Accessibility | Can everyone use it? | Arabic-first, screen-reader friendly |

**Constraints** — things you don't get to choose. *"Must use Azure." "Data must stay in-country." "Budget is free credits."* Constraints are gifts: they remove options and make decisions easier.

**Trade-off** — every decision buys one quality by spending another. Microservices buy independent deployment and spend operational simplicity. Private networking buys security and spends money and debuggability. **If you can't name what a decision costs, you don't understand it yet.**

**ADR (Architecture Decision Record)** — a short document:

```
# ADR-007: Use Service Bus for application events

Status: Accepted
Context: What problem, what constraints, what forces.
Options: A, B, C — with pros/cons for each.
Decision: We chose B.
Consequences: What becomes easier. What becomes harder. What we must now do.
```

ADRs matter because six months later nobody remembers *why*. In interviews, ADRs are your proof that you think in trade-offs.

**"It depends"** — the most famous architect answer. It is only a good answer if you immediately say **on what**. "It depends on whether the workflow must survive a server restart while waiting 5 days for a human" is architecture. "It depends" alone is a dodge.

### The architect's decision loop

1. **Clarify** the requirement and the quality attribute it really stresses.
2. **List constraints** (law, budget, skills, timeline, existing systems).
3. **Generate at least 3 options** — including the boring one and the "do nothing" one.
4. **Evaluate** each against the quality attributes that matter *most here*.
5. **Decide**, and write down what you're giving up.
6. **Define how you'd know you were wrong** (a metric, a threshold, a trigger to revisit).

### Self-test

- What's the difference between a functional requirement and a quality attribute?
- Name a decision in a project you've done that was *architecture* and one that wasn't.
- Why is "it depends" only acceptable when followed by a "on…"?

---

## 1. What a GCC government platform actually is

### The simple version

Imagine a shopping mall. Every shop (ministry) sells different things (services), but the mall provides shared things: one entrance (portal), one security desk (identity), one parking and payment system, one information desk (chatbot), one set of rules. Citizens don't want to know which ministry does what — they want to walk into one building.

That's the model most GCC governments have moved to: **one front door for all government services**.

### The concepts

**E-government / digital government** — delivering government services electronically. GCC countries have been among the fastest movers in the world here, driven by national visions (Saudi Vision 2030, Qatar National Vision 2030, UAE Centennial 2071, etc.).

**Whole-of-government** — treating the government as one organization from the citizen's point of view, rather than 30 separate ministries with 30 logins.

**Shared government platforms** — capabilities built once, centrally, and reused by every ministry. Typically:

| Shared capability | What it does | Real-world style example (illustrative) |
|---|---|---|
| National digital identity | One login for every government service | Saudi Nafath, UAE Pass, Qatar's national authentication service, Bahrain eKey |
| Unified portal / app | One place to find and request services | Saudi Absher, Abu Dhabi TAMM, Qatar Hukoomi, Dubai Now |
| Government payment gateway | One way to pay fees | Saudi SADAD, UAE e-Dirham / payment hubs |
| Government integration bus | Ministries exchange data securely | Saudi GSB (Government Service Bus) |
| Notification service | SMS/email/app notifications | National SMS gateways |
| National data registry | Single source of citizen/resident data | Population registers, national address registers |

**Digital government authority** — the central body that owns these shared platforms and sets standards for ministries. Saudi Arabia has a Digital Government Authority (DGA); the UAE has TDRA at federal level plus emirate-level bodies (e.g., Abu Dhabi's Department of Government Enablement, Digital Dubai); Qatar's digital government programme sits with its communications/IT ministry. **⚠ Verify** current names — these bodies get restructured.

**"Tell us once"** — a principle: the government should not ask a citizen for information it already has. If the population registry knows your address, the building permit form shouldn't ask for it. This requires ministries to **share data**, which requires **integration, consent, and audit**.

**Proactive services** — the next level: the government does things *before* you ask (e.g., automatically renewing a document, or registering a newborn's benefits). Several GCC countries publicly aim for this.

### What Sahl does

Sahl models the whole pattern in miniature:

- **Digital Government Authority (fictional)** — runs the Sahl platform, onboards ministries.
- **Ministry of Interior** — good-conduct certificate, service-center appointments.
- **Ministry of Municipality** — building permit (the flagship workflow).
- **Ministry of Commerce** — commercial license, fees, and the **company registry** (who may act for which company).
- **Mock National Authentication Service** — the fictional national ID login.
- **Mock payment gateway** — the fictional national payment hub.
- **Mock national registry** — ~1,000 synthetic citizens with QID-format IDs.

### What we didn't choose

- **A single-ministry app** — easier, but loses the hardest and most interesting problems: cross-ministry authorization, shared identity, integration, and data sharing.
- **Each ministry builds its own portal** — this is actually what many governments *started* with in the 2000s. It's why they later spent billions consolidating. Building it this way would teach the anti-pattern.

### What GCC governments actually do

Historically: each ministry had its own website, login, and back-office system, often built by different vendors (Oracle, SAP, Microsoft, local integrators). Citizens had dozens of usernames.

Then, roughly 2010s onward:
1. **National identity** came first — because nothing else can be shared without a common identity.
2. **Integration buses** connected ministries' existing systems instead of replacing them.
3. **Unified portals/apps** were built *on top* of ministries' systems: the portal is often a front-end that calls ministry back-ends through the integration layer.
4. **Cloud adoption** followed, but slowly and carefully, constrained by data-residency rules and security classifications — which is why hyperscalers (Microsoft, AWS, Google, Oracle) built in-country regions in the Gulf.

An important realism point: **in real life, the unified portal usually does not own the ministry's business logic.** The Municipality has its own permit system; the portal submits to it. In Sahl we put the ministries' logic *inside* our modular monolith to keep the PoC buildable — and we should say so in an ADR. In an interview, mentioning that difference shows you understand the real world.

### How to decide yourself

When scoping a government system, ask:
- Who are the **actors**? (citizens, residents, businesses, employees, other agencies, auditors)
- Which **shared national capabilities** already exist that I must integrate with rather than rebuild?
- Who **owns** each piece of data, legally?
- What is the **classification** of the data? (decides where it may live)
- Which entity **operates** the platform, and who is accountable when it breaks?

### Self-test

- Why does national identity have to come before a unified portal?
- What is "tell us once," and what three things does it require technically?
- In real life, does a unified portal usually contain each ministry's business logic? Why/why not?

---

## 2. Region, data residency and sovereignty

### The simple version

Data is like a citizen's passport: where it physically lives determines whose rules it must follow. Governments want their citizens' data to live **at home**, under **their** laws, where **they** can reach it in a crisis.

### The concepts

**Azure geography → region → availability zone → datacenter**

```
Geography (e.g., Qatar)
 └── Region (Qatar Central)              ← where you deploy
      ├── Availability Zone 1            ← one or more datacenters, independent power/cooling/network
      ├── Availability Zone 2
      └── Availability Zone 3
```

- **Region** — a set of datacenters in a metro area, connected by a low-latency network. You pick one when you create a resource.
- **Availability Zone (AZ)** — physically separate locations inside one region. Deploying "zone-redundant" means if one building floods, you keep running.
- **Paired region** — Microsoft's traditional model: each region had a partner ~hundreds of km away in the same geography, used for platform-level DR (e.g., geo-redundant storage replicates there, updates are rolled out to one pair member at a time). **Newer regions are often deployed without a pair**, relying on availability zones instead. Qatar Central is one of these (**⚠ Verify** current pairing status).
- **Non-regional / global services** — some services aren't tied to one region (Entra ID, Front Door, Traffic Manager, some parts of Static Web Apps). Their data may be processed globally. This matters a lot for residency.

**Data residency** — *where* data is stored at rest. "Citizen data must be stored in Qatar."

**Data sovereignty** — *whose laws* control the data. Data stored in Doha by a US company can, in principle, still be subject to US legal process (e.g., the US CLOUD Act). Sovereignty concerns are why "sovereign cloud" offerings exist.

**Data localization** — a law that *requires* residency (sometimes also requiring that processing, support staff, or keys stay in-country).

**Operational sovereignty** — who can operate the system: can foreign support engineers access it? Must operators be citizens/locally cleared?

**Key sovereignty** — who holds the encryption keys. If the government holds the keys in its own HSM, the cloud provider can store data it cannot read. (See Chapter 10 on Customer-Managed Keys and Chapter 14 on HSMs.)

**Sovereign cloud** — a cloud offering designed so that data, operations, and keys are controlled in-country, often operated by or with a local partner.

### The GCC cloud landscape (simplified, ⚠ Verify current status)

| Country | Azure | Other hyperscalers (examples) | Notes |
|---|---|---|---|
| Qatar | **Qatar Central** (Doha), launched 2022, with availability zones | Google Cloud Doha | Only in-country Azure option among Qatar/Kuwait/Bahrain/Oman for a while |
| UAE | **UAE North** (Dubai), **UAE Central** (Abu Dhabi, restricted) | AWS UAE, Google Dubai (announced/available), Oracle Abu Dhabi/Dubai | Microsoft also partners with a local provider for sovereign-cloud offerings |
| Saudi Arabia | Saudi region **announced** (check availability) | Google Dammam, Oracle Jeddah/Riyadh, local providers | Strong preference for in-kingdom and locally operated clouds for government |
| Bahrain | No Azure region | AWS Bahrain (first hyperscaler region in GCC, 2019) | "Cloud-first" government policy |
| Kuwait | Announced plans | Google (announced) | Historically mostly on-prem government |
| Oman | Announced plans | Oracle Muscat | |

### What Sahl does

| Environment | Region | Why |
|---|---|---|
| PoC (what we deploy) | **Germany West Central** | Every service available, cheap, credits work, zones available |
| "Production" (on paper) | **Qatar Central** | Data residency for citizen data |
| DR (on paper) | **None outside Qatar by default**; zone redundancy inside Qatar Central | Cross-border DR would break residency; documented as requiring an explicit approved exception |

This will be **ADR-001**. Its *Consequences* section must say: "Before production, re-validate every service's availability and SKU in Qatar Central; some features (e.g., certain AI models, zone-redundant SKUs) may not be available there."

### What we didn't choose

- **Deploy to Qatar Central for the PoC.** More authentic, but: fewer services/SKUs available, possibly higher prices, free/trial subscriptions may have restricted region access, and AI model availability is limited. We'd spend time fighting availability instead of learning architecture.
- **UAE North.** Closer to Qatar, but it doesn't teach anything Frankfurt doesn't, and it muddies the story (UAE data in a "Qatar" system).
- **Multi-region active-active.** Overkill for a citizen portal (see Chapter 19), and fundamentally in tension with single-country residency when there's only one in-country region.

### What GCC governments actually do

- **Classify data first, then place it.** Most GCC cybersecurity frameworks require data classification (e.g., public / internal / restricted / secret — names differ by country). Lower classifications may go to public cloud in-country; higher ones may require government-owned or sovereign infrastructure; the highest stay on-prem.
- **Prefer in-country regions** for anything with personal data. The arrival of in-country hyperscaler regions (Bahrain 2019, UAE 2019, Qatar 2022, …) was what unlocked large-scale government cloud adoption.
- **Approved provider lists.** Some regulators certify cloud providers/services (e.g., Saudi NCA's Cloud Cybersecurity Controls and provider registration with the communications regulator; Dubai's DESC CSP security standard). Government entities may only use approved services.
- **Sovereign arrangements.** For sensitive workloads, governments use sovereign-cloud models where a local entity operates or co-operates the cloud and controls keys.
- **Hybrid** is the norm: core registries on-prem in government datacenters, citizen-facing and analytics workloads increasingly in cloud, connected via private links (ExpressRoute or equivalents).

### How to decide yourself

1. What is the **data classification** of the most sensitive data the system holds?
2. What does **law/policy** say for that classification: in-country storage? in-country processing? local operators? keys held by the government?
3. Which regions satisfy that? Is every **service and SKU** I need available there? (Check "Products available by region".)
4. What about **global services** (identity, CDN, AI)? Where do they process data? Is that acceptable?
5. What's my **DR** story without leaving the country? Is zone redundancy enough? If not, who must approve a cross-border exception?

### Self-test

- Explain residency vs. sovereignty with an example.
- Why is "no paired region" a problem for a government, not just for an engineer?
- Name a global Azure service and explain why it's tricky for residency.
- If Qatar Central lacks a service you need, what are your three options?

---

## 3. Architecture style

### The simple version

- **Monolith** = one big restaurant kitchen where everyone cooks everything. Simple to run, but chefs bump into each other as it grows.
- **Modular monolith** = one kitchen, but with clearly separated stations (grill, pastry, salads). Each station has its own tools and staff, and they only pass plates through the window. Still one building, one rent.
- **Microservices** = separate food trucks. Each can open, close, and change its menu independently — but now you need roads, radios, and coordination, and if one truck is late the meal is late.

### The concepts

**Deployable unit** — the thing you ship. One container image = one deployable unit.

**Coupling** — how much one part must change when another changes. **Cohesion** — how much the things inside one part belong together. Good architecture = **high cohesion, low coupling**.

**Bounded context** (from Domain-Driven Design, DDD) — a boundary inside which words have one precise meaning. "Application" in *Permits* means a building-permit request with drawings; in *Payments* it might mean a payment attempt. Each bounded context gets its own model and, ideally, its own tables.

**Module boundaries in a modular monolith** — rules like:
- A module only exposes a **public interface** (service classes, events). No other module touches its internals.
- **No cross-module database joins.** Module A doesn't read module B's tables; it asks B through its interface.
- Modules communicate either by **direct in-process calls** (synchronous) or by **domain events** (asynchronous).
- Enforce it with tooling: architecture tests (e.g., Pest arch tests: "Permits must not depend on Payments\\Internal").

**Microservices** — each service owns its data and is deployed independently. Benefits: independent scaling, independent deployment, team autonomy, fault isolation, tech freedom. Costs: network latency and failure, distributed transactions (sagas), data duplication, observability complexity, many pipelines, versioning of APIs between services, and much higher operational cost.

**Distributed monolith** — the worst outcome: services that are deployed separately but must all change together. You pay the microservice cost and get monolith coupling.

**Conway's Law** — "Systems mirror the communication structure of the organizations that build them." Microservices make most sense when you have **multiple independent teams**. One developer (you) doing microservices is optimizing for a team structure that doesn't exist.

**Strangler fig pattern** — gradually extracting pieces from a monolith into services, routing traffic piece by piece. The usual path from modular monolith → microservices *when needed*.

### What Sahl does

- **Laravel API = modular monolith** with modules: `Identity`, `Citizens` (profile, consent), `Entities` (ministries), `Services` (catalog), `Applications` (common request lifecycle), `Permits`, `Certificates`, `Licensing`, `Appointments`, `Payments`, `Documents`, `Audit`.
- **Satellite services (Azure Functions or containers)** for work that is naturally asynchronous, bursty, or long-running:
  - PDF generation + signing
  - Workflow orchestration (permit approvals)
  - Payment webhook intake
  - AI assistant (RAG)
  - Mock National Authentication Service, mock payment gateway, mock Commerce registry API (these are *other organizations*, so they are separate deployables by nature)

### What we didn't choose

| Option | Why not, for Sahl |
|---|---|
| Plain monolith (no module rules) | Works, but you'd learn nothing about boundaries and it would become a "big ball of mud" by service #3 |
| Full microservices on AKS | One developer, credits budget, huge ops overhead; you'd spend most time on Kubernetes, not architecture decisions. Worth *describing* as the "Phase 3" evolution in an ADR |
| Microservices on Container Apps + Dapr | Good middle ground, but still pays the distributed-data cost (sagas everywhere) before we've proven we need it |

### What GCC governments actually do

- Large **COTS (commercial off-the-shelf) platforms** and big monoliths are very common: ERP (SAP, Oracle), CRM (Dynamics 365, Salesforce), BPM suites (Pega, Appian, IBM, Camunda), case-management products. Many ministry "systems" are configured products rather than custom code.
- **Integration layers** (ESBs, API gateways) glue these together — a "services" architecture at the *enterprise* level, even when each system is a monolith.
- Newer national platforms (super-apps, unified portals) are increasingly built cloud-native with microservices by large integrators, often on Kubernetes (including Azure Kubernetes Service or OpenShift), because many teams and vendors work on them in parallel — Conway's law again.
- Vendor lock-in and procurement shape architecture as much as technology does: a ministry may be on a 5-year contract with a product vendor.

### How to decide yourself

- How many **teams** will work on this? Do they need to deploy independently?
- Do parts have **very different scaling** or **availability** needs?
- Do parts have different **security/compliance** zones (e.g., a payment component in PCI scope)?
- Can I keep **one database transaction** where business consistency matters? (If I split, I need sagas.)
- What's my **ops capacity**? Who's on call for 15 services?

A useful rule: **start modular, extract when a module has a clear, measured reason to be separate** (different scale, different team, different compliance boundary, different release cadence).

### Self-test

- Define bounded context with a Sahl example.
- What's a distributed monolith and how do you accidentally build one?
- Give two legitimate reasons to extract `Payments` into its own service later.
- How does Conway's Law apply to a one-person project?

---

## 4. Compute: where the code runs

> **This is an open decision — you will make it in Step 2.** This chapter gives you everything you need to decide.

### The simple version

Compute options are like ways to get a place to live:
- **Virtual machine** = buying a house. Total control; you fix the plumbing.
- **App Service** = renting a furnished apartment. Move in with your bags (code); the landlord handles maintenance. Some rules about what you can install.
- **Container Apps** = a serviced apartment that also scales: bring your own furniture packed in a standard box (container); more rooms appear automatically when guests arrive; rooms disappear when empty.
- **AKS (Kubernetes)** = you manage an apartment building. Huge flexibility, but you're the building manager.
- **Functions** = hotel rooms by the hour. Pay only while you're using them; you have to fit into how hotels work.

### The concepts

**IaaS / PaaS / Serverless / CaaS**
- **IaaS** (Infrastructure as a Service) — you get VMs; you manage OS, patching, runtime.
- **PaaS** (Platform as a Service) — you give code or a container; Azure manages the OS and runtime.
- **CaaS** (Containers as a Service) — PaaS specialized for containers.
- **Serverless** — scale to zero, pay per use, event-driven.

**Container** — your app plus its runtime and dependencies packaged as an image. Same image runs on your laptop (Docker Compose) and in Azure. Stored in a **container registry** (Azure Container Registry, ACR).

**Scale up vs scale out** — up = bigger machine; out = more machines. Web APIs should be **stateless** so they can scale out (sessions/cache in Redis, files in Blob, not on local disk). For Laravel that means: no local `storage/app` files, no file-based sessions/cache in production.

**Autoscaling** — adding/removing instances based on rules (CPU, HTTP concurrency, queue length). Container Apps uses **KEDA** (Kubernetes Event-Driven Autoscaling) under the hood, so it can scale on Service Bus queue length — great for workers.

**Cold start** — the delay when a scaled-to-zero app receives its first request. Fine for background jobs; annoying for a citizen-facing API.

**VNet integration** — the app can reach private resources (database via private endpoint). **Inbound private access** — the app itself has no public endpoint. Different features, often different SKUs.

### The options for the Laravel API

| | **App Service (Linux)** | **Container Apps** | **AKS** |
|---|---|---|---|
| What you deploy | Code (PHP runtime built in) or a container | Container | Containers + Kubernetes manifests |
| Scale | Instance count, autoscale rules | 0..N replicas, HTTP/KEDA scaling | Anything |
| Scale to zero | No (dedicated plans) | Yes | With effort |
| Background workers (queues) | WebJobs or separate app | Separate container app or "job", scales on queue length | Deployments |
| Private networking | VNet integration (Basic+ / Standard+), private endpoints (Premium tiers) | VNet-injected environment; internal-only environments | Full control |
| Ops burden | Lowest | Low | High |
| Cost floor | ~one always-on plan | Can be near-zero at idle (consumption) | Nodes always running |
| Learning value | Classic AZ-104/AZ-305 material | Modern cloud-native, Dapr, KEDA, revisions | Deepest, but mostly Kubernetes |

**Laravel-specific considerations:**
- Laravel needs PHP-FPM + a web server (Nginx) + queue workers + scheduler. On **App Service**, the built-in PHP image runs the web part; workers need another mechanism. On **Container Apps**, you build one image and run it three ways: web, `queue:work`, and `schedule:work` (or Container Apps **jobs** with cron).
- **Laravel Octane** (Swoole/RoadRunner/FrankenPHP) keeps the app in memory for performance — works well in containers.

### Functions hosting plans (for the satellite services)

| Plan | Scale to zero | VNet | Notes |
|---|---|---|---|
| Consumption (classic) | Yes | No | Cheapest, being superseded |
| **Flex Consumption** | Yes | **Yes** | Modern default for event-driven + private networking ⚠ Verify regional availability |
| Premium (Elastic) | No (pre-warmed) | Yes | No cold start, always-on cost |
| Dedicated (App Service plan) | No | Yes | Run on existing plan |
| Container Apps hosting | Yes | Yes | Functions in a container on Container Apps |

### What GCC governments actually do

- Large ministries and integrators often standardize on **Kubernetes** (AKS, OpenShift, or on-prem Kubernetes) because it's portable between public cloud, sovereign cloud and on-prem — a big deal when residency rules might force a move.
- PaaS like App Service is common for smaller, citizen-facing sites and internal apps.
- **Portability** is a real government concern: "If the regulator changes the rules, can we move this workload on-prem?" Containers help answer "yes".

### How to decide yourself (questions for Step 2)

1. Do I need **scale to zero** (cost) or **no cold starts** (UX)?
2. How will I run **queue workers and the scheduler**?
3. Which option gives **private inbound + outbound** networking at an affordable SKU?
4. Do I want **portability** (containers) as a stated architectural goal?
5. Which option teaches me the most **relevant** skills for the roles I'm targeting?

### Self-test

- Why must a scaled-out Laravel app not store sessions on local disk?
- What is KEDA and why does it matter for a queue worker?
- Explain cold start and where it matters/doesn't in Sahl.
- Why might a government prefer containers even when PaaS is simpler?

---

## 5. Frontend

### The simple version

The frontend is the shop window and counter. Sahl has two shops: the **public counter** for citizens and the **back office** for employees. They must never share a door, because what employees can see is far more sensitive.

### The concepts

**SPA (Single Page Application)** — the browser downloads a JavaScript app once; it then fetches data from APIs and renders screens itself.

**SSR (Server-Side Rendering)** — the server sends ready HTML (Blade, Inertia's SSR, Next.js). Better first paint and SEO; more server work.

**Inertia** — a bridge where Laravel controllers return page components; auth is usually the Laravel **session cookie**. Great DX, but the "API" isn't a real public API.

**BFF (Backend-for-Frontend)** — a server that sits between the browser and APIs, holding tokens server-side and giving the browser only an HttpOnly session cookie. Considered the **most secure pattern for browser apps** handling sensitive data, because tokens never touch JavaScript.

**Token storage in SPAs** — the SPA must hold an access token to call the API. Where?
- In memory → safest, lost on refresh (MSAL re-acquires silently).
- sessionStorage → MSAL's default; readable by any script on the page, so **XSS** = token theft.
- localStorage → persists longer; same XSS risk, longer exposure.
This is the core security weakness of SPAs and why **CSP (Content Security Policy)** and strict dependency hygiene matter.

**XSS (Cross-Site Scripting)** — attacker gets JavaScript to run on your page. With SPAs that hold tokens, XSS is catastrophic. Mitigations: React escapes by default, avoid `dangerouslySetInnerHTML`, strict CSP, Subresource Integrity, minimal third-party scripts.

**CORS** — browser rule: a page from `citizen.sahl.example` can only call `api.sahl.example` if the API explicitly allows that origin.

**i18n / l10n** — internationalization (making the app translatable) and localization (the actual Arabic/English content, number and date formats).

**RTL (Right-to-Left)** — Arabic layout flips: navigation on the right, text aligned right, icons that imply direction (arrows, "back") mirrored. Use **CSS logical properties** (`margin-inline-start` instead of `margin-left`; in Tailwind `ms-4`, `pe-2`, `start-0`) and `dir="rtl"` on `<html>`.

**Bidirectional text (bidi)** — Arabic text containing English words or numbers (e.g., a QID or a permit number). Needs care: `<bdi>`, `dir="auto"`, and testing.

**Arabic typography** — Arabic needs fonts designed for it (e.g., IBM Plex Sans Arabic, Noto Kufi/Naskh Arabic, Tajawal, Cairo), larger line-height, no letter-spacing (it breaks joined letters).

**Calendars** — Hijri (Umm al-Qura variant in Saudi Arabia) and Gregorian. GCC forms often show both. `Intl.DateTimeFormat('ar-QA', { calendar: 'islamic-umalqura' })` handles conversion in the browser.

**Numerals** — Western (0-9) vs Eastern Arabic (٠-٩). Many Gulf government sites use Western numerals even in Arabic UI; make it a deliberate choice.

**Accessibility (a11y)** — **WCAG 2.1/2.2 AA** is the common target: keyboard navigation, screen reader labels, contrast, focus states, error messages tied to fields. Government sites are often legally or policy-bound to accessibility standards.

**Design system** — a shared set of components and rules. Several GCC governments publish official **government design systems** so all ministry sites look and behave consistently (e.g., the UAE Design System; Saudi Arabia's DGA unified design guidelines — **⚠ Verify** current names/links). For a portfolio, studying one of these and matching its principles is a big realism win.

### What Sahl does

- **Laravel + Inertia + React (TypeScript)**: Laravel is the **BFF**. The browser only ever holds an **HttpOnly, Secure, SameSite session cookie**, and tokens never reach JavaScript.
- **One codebase, one container image, two deployments**:
  - `citizen` surface: citizen routes only, public through Front Door + WAF, signs in via **Entra External ID** (→ mock national ID).
  - `backoffice` surface: employee routes only, **internal-only ingress** (reachable from the "government network": VPN/Bastion in the gov profile), signs in via **Entra workforce**.
  - An env variable (`APP_SURFACE=citizen|backoffice`) decides which route groups, auth guard and Entra app registration load. Separate hostnames → separate cookies.
- **Server-side OIDC**: Laravel is a **confidential client** (Authorization Code flow with PKCE, authenticating to Entra with a certificate or federated credential rather than a plain secret where possible).
- **Sessions in Redis** (not files) so the app can scale out to multiple replicas.
- **CSRF protection** via Laravel's built-in tokens (cookies make CSRF relevant again; Inertia/axios handle the token automatically).
- UI stack: **React + TypeScript + shadcn/ui + Tailwind**, **i18next** for translations (Laravel lang files shared with the frontend), **Arabic default**, English toggle, `dir` switches on language.
- Tailwind **logical properties** only; a lint rule bans `ml-`/`mr-`/`pl-`/`pr-`/`left-`/`right-` utilities.
- Dual-calendar date component; WCAG AA as a goal; strict **CSP** (still important — XSS can't steal tokens anymore, but it can still act as the user inside the page).
- **APIM is used where it belongs: system-to-system APIs** (inter-ministry registry APIs, payment webhooks, any future mobile/public API), not in front of browser page traffic.

> **Decision history (a lesson in itself):** this decision was first made as "two React SPAs + Laravel API" (to practice MSAL and token flows), then **reversed** in favor of Inertia-as-BFF after weighing security (tokens out of the browser), focus (more time for Azure) and realism (APIM for system-to-system APIs). In ADR terms the first ADR is marked **Superseded by ADR-00x**, not deleted. Architects change their minds; good ones leave a trail explaining why.

### What we didn't choose

| Option | Why not |
|---|---|
| Two React SPAs + Laravel API (original plan) | Tokens live in browser storage (XSS = token theft); lots of frontend plumbing (router, data fetching cache, MSAL, CORS) that competes with Azure learning time. Still the right choice when many clients (web, mobile, partners) share one API — documented as the path if a mobile app is added |
| Next.js as BFF | Same security benefits as Inertia, but a second server stack to run and learn |
| Inertia, single deployment for both surfaces | Simplest, but the back-office would be exposed on the same public endpoint as the citizen portal |
| MUI / Fluent UI | Both fine; shadcn gives you owned, editable components and clean Tailwind-based RTL |

### What GCC governments actually do

- Citizen channels are increasingly **mobile-first super-apps** (one app for many services), with the web portal second.
- Back-office systems are often **separate products** (case management, CRM) used only on the government network or via VPN/zero-trust access.
- Arabic is the official language; Arabic-first, bilingual UX is expected. Accessibility and "digital inclusion" (elderly, people of determination) are explicit goals in several GCC digital strategies.
- Many use the **BFF / gateway + session** pattern for sensitive portals, plus device binding in mobile apps.

### How to decide yourself

- Who are the users and what **trust zone** are they in (internet vs government network)?
- How sensitive is what the frontend can trigger? (Higher → consider BFF.)
- Is there a **public API** or **mobile app** requirement? (Pushes toward SPA/mobile + API, often *alongside* a BFF for the web.)
- **SEO** needed? (Public service catalog pages benefit from SSR/static generation.)
- Team skills and existing design system?

### Self-test

- Why is XSS more dangerous in an SPA that holds tokens than in a cookie-session Blade app?
- What does a BFF change about where tokens live?
- What's the difference between `ml-4` and `ms-4` and why does it matter here?
- Why separate the citizen and back-office deployments instead of hiding screens by role?
- Why does Inertia count as a BFF, and what new concern (hint: cookies) does it bring back?

---

## 6. Identity fundamentals

> Identity is **the** most important layer of a government system. If you master one chapter for interviews, master this one and the next two.

### The simple version

- **Authentication (AuthN)** = *"Who are you?"* — showing your ID card at the door.
- **Authorization (AuthZ)** = *"What are you allowed to do?"* — the guard checking whether your badge opens *this* room.
- **A token** = a visitor badge printed by a trusted reception desk. Each room's guard doesn't call reception; they just check the badge's hologram (signature), expiry, and which rooms it lists.

### The concepts

**Identity Provider (IdP)** — the reception desk. It authenticates users and issues tokens. Examples: Entra ID, Google, a national digital ID.

**Relying Party (RP) / client** — the app that trusts the IdP (our Laravel app, in both surfaces).

**Resource server** — the API that accepts tokens (our Laravel API).

**OAuth 2.0** — a framework for **delegated authorization**: "Let this app call this API *on my behalf*, with these permissions (scopes)." It defines **flows** (grant types) and **access tokens**. OAuth by itself does *not* say who the user is.

**OpenID Connect (OIDC)** — an identity layer on top of OAuth 2.0. Adds the **ID token** (who the user is), a standard **userinfo** endpoint, and **discovery** (`/.well-known/openid-configuration`), which publishes the IdP's endpoints and signing keys.

**SAML 2.0** — an older XML-based federation standard. Still very common in governments and enterprises. OIDC is the modern default; you will meet both.

**The three tokens**

| Token | For | Audience | Contains | Lifetime (typical) |
|---|---|---|---|---|
| **ID token** | The client app: "who logged in" | The client | Name, user ID, auth time, how they authenticated | Minutes–1 h |
| **Access token** | The API: "may this caller do X" | The API (`aud`) | Scopes or roles, subject, tenant, expiry | ~1 h |
| **Refresh token** | Getting new access tokens without re-login | The IdP | Opaque | Hours–days (SPAs: shorter, e.g. 24 h in Entra) |

**Rule:** an API must **never** accept an ID token as an access token. Different audience, different purpose.

**JWT (JSON Web Token)** — `header.payload.signature`, each Base64URL-encoded. The API validates:
1. **Signature** — using the IdP's public keys from the **JWKS** endpoint (found via discovery).
2. **`iss`** (issuer) — the token came from the IdP we trust.
3. **`aud`** (audience) — the token was issued **for this API**, not some other app.
4. **`exp` / `nbf`** — not expired, already valid.
5. **Scopes/roles** — the caller has permission for this endpoint.

**Claims** — the fields in a token: `sub` (stable user ID), `oid` (Entra object ID), `tid` (tenant), `roles`, `scp` (scopes), `name`, `acr`/`amr` (how strongly the user authenticated), plus custom ones like `qid`.

**Scopes** (delegated permissions) — what the **app** may do **on behalf of the user**: `Applications.Read`, `Applications.Submit`. The effective permission = what the user can do ∩ what the app was granted.

**App roles** — roles defined on an application and assigned to users, groups, or other apps: `Reviewer`, `Approver`. They appear in the `roles` claim.

**OAuth flows (grant types) you need to know**

| Flow | Who uses it | Sahl use |
|---|---|---|
| **Authorization Code (+ PKCE)** | Web apps (confidential), SPAs & mobile (public) | Laravel citizen + back-office surfaces (server-side, confidential client) |
| **Client Credentials** | Service → service, no user | Laravel → Commerce registry API; Functions → API |
| **On-Behalf-Of (OBO)** | API calls another API *as the same user* | Possible: API → another ministry API carrying the citizen's identity |
| **Device Code** | TVs/CLIs without a browser | `az login --use-device-code` |
| **Implicit** | Old SPAs | **Deprecated** — never use |
| **Resource Owner Password** | Legacy | **Never use** |

**PKCE (Proof Key for Code Exchange)** — the SPA creates a random secret, sends its hash with the login request, and the raw secret when exchanging the code for tokens. Stops attackers who intercept the authorization code. Mandatory for public clients.

**Public vs confidential client** — a **public** client (SPA, mobile app) cannot keep a secret; a **confidential** client (server) can (client secret or, better, certificate / managed identity / workload identity federation).

**Session vs token** — the IdP keeps its own **SSO session** (cookie on the IdP domain). That's why a citizen logged into one government site can enter another without typing the password again — **Single Sign-On**.

**Single Logout** — logging out of one app logs you out of all. Hard to do perfectly; governments care because of shared devices (internet cafés, family phones).

**MFA (Multi-Factor Authentication)** — something you know (password), have (phone, token), or are (biometric). National digital IDs usually use a **mobile app with biometrics** as the second factor.

**Level of Assurance (LoA)** — how confident we are that the person is who they claim. Two parts:
- **Identity proofing** — how well was the identity verified at enrolment? (Self-asserted email vs. in-person with a national ID card.)
- **Authentication strength** — how strong was this login? (Password vs. MFA vs. smart card/PKI.)
Frameworks: NIST SP 800-63 (IAL/AAL/FAL), EU eIDAS (low/substantial/high). National IdPs often return the level in the `acr` claim. **Services require a minimum LoA**: checking a service fee might accept low; signing a property transfer requires high.

### Self-test

- Explain AuthN vs AuthZ in one sentence each.
- Which five things must an API check on a JWT?
- Why must an API never accept an ID token?
- What problem does PKCE solve?
- Which OAuth flow does Laravel use to call the Commerce registry API, and why?
- What's LoA and why would a government service require a minimum level?

---

## 7. National identity, federation and Entra

### The simple version

The citizen already has a government-issued digital ID (like a national ID card, but online). Sahl doesn't issue its own logins for citizens. Instead it says: *"Go prove who you are to the National Authentication Service; I trust what they tell me."* That trust relationship is **federation**.

Employees are different: they're staff, so they log in with their **work account** in the government's employee directory.

### The concepts

**Federation** — one system (the relying party or a broker) trusts identities asserted by another system (the IdP). Set up by exchanging metadata: endpoints, signing keys, client IDs.

**Identity broker** — a middle layer that federates with multiple upstream IdPs and issues its own uniform tokens to apps. Apps integrate once with the broker; the broker deals with each IdP's quirks. Examples: Entra External ID, Keycloak, Auth0/Okta, Ping, ForgeRock.

**Claims mapping / transformation** — converting the upstream IdP's claims into what your apps expect (e.g., national IdP's `national_id` → our `qid`; `name_ar`, `name_en`, nationality, date of birth).

**Just-in-time (JIT) provisioning** — the first time a federated user logs in, create their local profile automatically.

**Account linking** — connecting one person's identities from different IdPs (e.g., a visitor who later gets a national ID). Hard to do safely; usually keyed on a verified identifier.

**Tenant (Entra)** — an isolated directory instance with its own users, apps, and policies.

**Microsoft Entra ID (workforce)** — the directory for an organization's **employees**. Features: Conditional Access, MFA, groups, app roles, PIM, sign-in logs, managed identities.

**Microsoft Entra External ID** — Microsoft's **customer identity (CIAM)** product: a separate *external tenant* for citizens/customers, with sign-up/sign-in **user flows**, branding, social and **custom OIDC identity providers**, and **custom authentication extensions** (call your API during token issuance to add claims). It's the successor to **Azure AD B2C** for new projects. **⚠ Verify** current feature set — this product evolves quickly.

**App registration vs enterprise application (service principal)**
- **App registration** = the *definition* of an app (its ID, redirect URIs, exposed scopes, app roles). Lives in the home tenant.
- **Enterprise application / service principal** = the *instance* of that app in a tenant, where users/groups get assigned and consent is recorded.

**Conditional Access** — policies like "Back-office access requires MFA and a compliant device" or "Block sign-ins from outside the country." (Requires Entra ID P1.)

**PIM (Privileged Identity Management)** — admins don't hold permanent admin rights; they **activate** them for a limited time with justification/approval. (Requires P2.) Government auditors love this.

**Managed Identity** — an identity for an Azure resource (system-assigned: tied to one resource's lifecycle; user-assigned: standalone, reusable). Used to call Key Vault, Storage, Postgres, Service Bus **without secrets**.

**Workload identity federation** — an external workload (GitHub Actions, Kubernetes pod) exchanges *its own* token for an Entra token, so no secret is stored. Same idea as managed identity, but for things outside Azure.

### What Sahl does

```
CITIZEN (browser)                                           EMPLOYEE (browser, gov network)
   │ session cookie only                                        │ session cookie only
   ▼                                                            ▼
Laravel "citizen" surface                                  Laravel "backoffice" surface
(confidential client, Auth Code)                           (confidential client, Auth Code)
   │ redirect                                                   │ redirect
   ▼                                                            ▼
Entra External ID (external tenant)                        Entra ID (workforce tenant)
   │ federates via OIDC                                         app roles + groups
   ▼                                                            Conditional Access (MFA)
Mock National Authentication Service
(our own OIDC provider, with fake citizens + "approve on phone" + acr level)
   │
   ▼  back to Laravel with an authorization code
Laravel exchanges the code SERVER-SIDE for tokens, validates the ID token,
maps claims, creates/updates the local user (JIT), starts a session.
Tokens stay on the server; the browser only gets the session cookie.
```

- **Two tenants**: an **external tenant** (citizens) and a **workforce tenant** (employees), each matched to one Laravel surface with its own app registration and auth guard.
- **Mock National Authentication Service**: a separate small app acting as an **OIDC provider**, with fake citizens from the synthetic registry, a fake "approve on your phone" step, and an `acr` claim indicating the assurance level.
- **External ID** federates to it as a **custom OIDC identity provider** and maps claims (`qid`, names, nationality).
- Optionally a **custom authentication extension** enriches the token from our registry at sign-in.
- Laravel does **JIT provisioning** of the citizen profile on first login.
- **System-to-system calls** (Laravel → Commerce registry, → National Registry) use the **client credentials** flow through APIM — no user token involved.

### What we didn't choose

| Option | Why not |
|---|---|
| Laravel issues its own citizen logins (Sanctum/Passport) | Unrealistic: no GCC portal does this for citizens; skips the most valuable Azure identity learning |
| External ID with email/password only | Easy, but loses federation — the real-world integration skill |
| Apps integrate **directly** with the national IdP (no broker) | Very realistic! Many government portals do exactly this. We use a broker because it also teaches CIAM and gives one token format for our APIs. Mention this in the ADR as a valid alternative |
| One tenant for both citizens and employees | Mixes populations with completely different policies, licensing, and risk. Microsoft explicitly separates workforce and external tenants |
| Azure AD B2C | Being phased out for new customers in favor of External ID ⚠ Verify |
| Keycloak (self-hosted) | Popular in governments (open source, on-prem friendly) but you'd operate it yourself; less Azure learning |

### What GCC governments actually do

- **National digital identity is centralized** and run by a government body (often under the interior ministry or the digital government authority). Ministries **integrate** with it via **SAML or OIDC**; they don't build their own citizen logins.
- National IdPs typically use a **mobile app** for authentication (push notification + biometric/PIN), sometimes backed by **PKI certificates on the national ID card** or in the phone's secure element for high-assurance actions and digital signing.
- They return identity attributes from the **population registry** (name in Arabic and English, nationality, date of birth, ID number), and an assurance level.
- **Onboarding to the national IdP** is a formal process: the ministry registers as a relying party, passes security review, gets test credentials in a staging environment, then production. Your mock IdP should have a "staging" and "production" config to model this.
- Employees: most government entities use a **Microsoft-based** workforce identity (Active Directory on-prem, synced to Entra ID — **hybrid identity** via Entra Connect / Cloud Sync), with MFA and increasingly Conditional Access.
- Businesses: a person logs in with their **personal national ID**, then acts for a company based on the **commercial registry** (Chapter 8). There's usually no separate "company login."

### How to decide yourself

1. Is there an **existing authoritative IdP** for this population? (Then integrate, don't build.)
2. How many **populations** (citizens, residents, visitors, employees, partners)? Separate directories?
3. What **assurance level** does each service need?
4. Do I need a **broker** (multiple IdPs, uniform tokens, custom policies) or is direct integration enough?
5. Which **attributes** do I need, who is authoritative for each, and how fresh must they be?

### Self-test

- What's federation, and what gets exchanged to set it up?
- Draw the citizen login flow from memory, including where PKCE happens.
- Why two Entra tenants?
- What's the difference between an app registration and an enterprise application?
- Why might a real government skip the broker and integrate apps directly with the national IdP?
- What is hybrid identity and why is it so common in government?

---

## 8. Authorization

### The simple version

Being logged in only proves who you are. Authorization asks a much more detailed question: *"May **this** person do **this action** on **this specific record**, **right now**, **in this context**?"*

A Municipality reviewer may see building permits — but only Municipality ones, only those assigned to their department, never one they submitted themselves, and they may not approve what they reviewed.

### The concepts

**RBAC (Role-Based Access Control)** — permissions attach to roles; users get roles. Simple, auditable. Breaks down when permissions depend on attributes of the data ("only *my* ministry's records").

**ABAC (Attribute-Based Access Control)** — rules over attributes of the **user**, **resource**, **action**, and **environment**: `user.entity == resource.entity && user.department in resource.assignedDepartments && time is working hours`.

**ReBAC (Relationship-Based Access Control)** — permission comes from relationships: "user is *authorized signatory of* company X, and application *belongs to* company X." (Google Zanzibar-style systems, OpenFGA.)

**PBAC (Policy-Based Access Control)** — authorization rules written as policies evaluated by an engine (OPA/Rego, Cedar), separate from code.

In practice, real systems combine them: **RBAC for coarse permissions, ABAC/ReBAC for data-level checks.**

**Coarse-grained vs fine-grained**
- Coarse: "Reviewers may call `GET /permits`" → can be checked at **APIM** or route middleware.
- Fine: "…but only permits of their entity, assigned to them" → must be checked in the **application** with the data.

**Where authorization happens (defense in depth)**
1. **APIM** — valid token, right audience, required scope/role for the route.
2. **Laravel middleware** — population (citizen vs employee), role present.
3. **Laravel policies/gates** — record-level decision.
4. **Query scoping** — users can't even *list* records outside their scope (global scopes, or Postgres **Row-Level Security** as an extra layer).

**Principle of least privilege** — every identity gets the minimum permissions needed.

**Separation of Duties (SoD)** — critical processes require more than one person: a reviewer can't approve their own review; an employee can't process an application they (or their family) submitted; an admin can't grant themselves roles.

**Maker–checker (four-eyes principle)** — one person makes a change, another approves it. Very common in government and finance.

**Delegation / acting on behalf of** — a user acts for another party (a company, a family member, a legal representative). The token says *who the user is*; the system must also know *who they're acting for* and verify that relationship.

**Break-glass access** — emergency access that bypasses normal controls, heavily logged and reviewed.

### What Sahl does

**Employee roles (Entra app roles on the Sahl API):**

| Role | Can do | Scope |
|---|---|---|
| Platform Admin | Onboard entities, manage service catalog | Whole platform (Digital Government Authority) |
| Entity Admin | Manage their ministry's services and staff assignments | Their entity |
| Reviewer | Review assigned applications, request corrections | Their entity + department |
| Approver | Final approval/rejection | Their entity; never on applications they reviewed |
| Service Center Agent | Manage appointments, assist walk-in citizens | Their entity's service centers |
| Auditor | Read-only access to applications and the audit log | Assigned entity or platform-wide |

- **Which entity** an employee belongs to comes from **Entra group membership** (`grp-sahl-moi-reviewers`, …) → group claim or Graph lookup → mapped to `entity_id` in Laravel.
- **Laravel policies** enforce record-level rules and SoD.
- **Citizen authorization**: a citizen can see their own applications; when **acting for a company**, Laravel calls the **Ministry of Commerce registry API** (via APIM, client credentials) to confirm they're an authorized signatory, caches the result briefly, and records the delegation context in every audit event.
- **Least privilege for workloads**: each managed identity gets narrow Azure RBAC roles (e.g., `Key Vault Crypto User` only on the signing key, `Storage Blob Data Contributor` only on the documents container).

### What we didn't choose

| Option | Why not |
|---|---|
| Company roles as token claims from the IdP | Couples identity to business data; claims go stale when a signatory is removed mid-session; real registries are owned by commerce ministries, not IdPs |
| A dedicated policy engine (OPA, Cedar, OpenFGA) | Excellent for large platforms; overkill for v1. Note as an evolution in an ADR ("when authorization rules exceed what policies can express cleanly") |
| Roles stored only in Laravel's DB | Loses Entra governance (access reviews, PIM, central offboarding). In government, **when an employee leaves, disabling them in one directory must cut all access** |

### What GCC governments actually do

- **Centralized employee identity** with roles managed in the directory, plus application-specific permissions inside each system.
- **Delegation for businesses** relies on commercial-registry data (who are the owners, managers, authorized signatories) — sometimes extended with explicit delegation features ("authorize this employee of my company to submit applications").
- **Family delegation** (a parent acting for a child, a guardian for a dependent) is common in GCC services and is modeled on population-registry relationships.
- Regulators require **periodic access reviews** (re-certify who has what), SoD, and privileged-access management.

### How to decide yourself

- Which decisions can be made from the **token alone** (coarse) vs need the **data** (fine)?
- Who is the **authoritative source** for each relationship (employee→ministry, person→company)?
- How **fresh** must the relationship be? (Cache how long? What if it's revoked mid-session?)
- What **SoD** rules does the business process demand?
- How do you **audit** the decision (who, acting for whom, why allowed)?

### Self-test

- Give an example of a rule RBAC can't express but ABAC can.
- Where does Sahl check authorization (four layers)?
- Why does the Commerce registry decide company delegation, not the IdP?
- What is maker–checker? Where does Sahl use it?
- What happens to an employee's access when they leave, and why does that argue for Entra app roles?

---

## 9. Multi-tenancy

### The simple version

One building, many companies renting offices. Each company must only enter its own offices, but they share the elevators, the security desk, and the electricity bill. The question is how thick the walls between offices are.

### The concepts

**Tenant** — here, a **government entity** (ministry). (Careful: this is a different meaning from an **Entra tenant**.)

**Tenancy models**

| Model | Description | Isolation | Cost | Ops |
|---|---|---|---|---|
| **Pool (shared)** | All tenants share app and database; rows tagged with `entity_id` | Logical (code-enforced) | Lowest | Simplest |
| **Bridge** | Shared app, separate schema or database per tenant | Stronger | Medium | Medium |
| **Silo** | Separate deployment (app + DB, maybe subscription) per tenant | Strongest | Highest | Hardest (N copies) |

**Noisy neighbor** — one tenant's load affecting others. Mitigated with rate limits per tenant (APIM products/subscriptions), separate queues, or silos for heavy tenants.

**Tenant context** — every request, job, and event must carry `entity_id` so nothing runs "tenant-less."

**Cross-tenant data access** — in government, *some* sharing is required (Municipality needs to know whether Civil Defense approved). It must be **explicit**, through defined interfaces, **logged**, and ideally **consented** when personal data is involved.

### What Sahl does

- **Pool model** for the PoC: one database, `entity_id` on all entity-owned tables, Laravel global scopes + policies, plus optional **Postgres Row-Level Security** as a second wall.
- Shared **platform data** (citizen profile, consent, service catalog) owned by the platform; **entity data** (applications, reviews) owned by each ministry.
- **Cross-entity** collaboration (e.g., permit parallel reviews by Civil Defense and Electricity) via explicit workflow assignments, never by giving one ministry blanket read access to another.
- ADR describing when an entity would move to **bridge/silo** (e.g., Interior requires its own database for classified data).

### What we didn't choose

- **Database per ministry** — stronger isolation but triples costs and migrations; not needed for a fictional PoC. Documented as the upgrade path.
- **Separate deployments per ministry** — that's the 2000s model the unified-platform movement is trying to escape.

### What GCC governments actually do

- National platforms are typically **shared services** consumed by ministries, with ministries' core systems remaining **separate** (silo by nature — they were built independently).
- Highly sensitive entities (interior, defense, intelligence, central bank) usually keep **fully separate** infrastructure. Their data is shared through tightly controlled interfaces, not through shared databases.

### Self-test

- Pool vs bridge vs silo: define, and give one reason to pick each.
- What's the "tenant context" and where can it get lost (hint: queued jobs)?
- How should Municipality see Civil Defense's review result without seeing all Civil Defense data?

---

## 10. Data

### The simple version

The database is the government's filing room. It must never lose a file (durability), must only let the right clerks open the right cabinets (access control), must lock the cabinets even if someone steals the whole room (encryption), and must keep copies so a fire doesn't end the government (backups).

### The concepts

**Managed database (PaaS)** — Azure runs the database engine: patching, backups, high availability. You manage schema, queries, and access.

**Azure Database for PostgreSQL – Flexible Server** — managed Postgres with:
- **Compute tiers**: Burstable (cheap, CPU credits — ideal for dev/PoC), General Purpose, Memory Optimized.
- **High availability**: same-zone or **zone-redundant HA** (a standby in another AZ, synchronous replication, automatic failover).
- **Backups**: automatic, with **point-in-time restore (PITR)** within the retention window (7–35 days); optional geo-redundant backup (careful: residency!).
- **Networking**: **private access via VNet integration** (server injected into a delegated subnet) or **private endpoint**; or public access with firewall rules.
- **Authentication**: Postgres passwords and/or **Microsoft Entra authentication** (managed identities log in with tokens).
- **Read replicas** for read scaling/reporting.

**RPO / RTO** (see Chapter 19) — how much data you can lose / how long you can be down.

**Encryption**
- **In transit** — TLS between app and DB (enforce `sslmode=require`/`verify-full`).
- **At rest** — Azure encrypts storage by default with **platform-managed keys (PMK)**.
- **Customer-Managed Keys (CMK)** — the encryption key lives in **your Key Vault/Managed HSM**; Azure uses it to wrap the data key. If you revoke the key, the data becomes unreadable — **crypto-shredding**. Governments often require CMK for sensitive data so they control the "off switch."
- **Application-level / column encryption** — encrypt particularly sensitive fields (e.g., medical notes) in the app before storing (Laravel `encrypted` casts), so even DBAs can't read them. Trade-off: you can't query/index them normally.

**Data classification** — labeling data by sensitivity (see Chapter 18). Drives encryption, access, retention, residency, and logging.

**PII (Personally Identifiable Information)** — data identifying a person: name, QID, phone, address, biometrics. **Sensitive personal data** (health, religion, criminal records, children's data…) has stricter rules in most privacy laws — a good-conduct certificate touches criminal-record data!

**Data minimization** — collect and keep only what's necessary.

**Retention** — how long data is kept, and what happens after (deletion, archival, anonymization). Government retention is often long (archival laws) and must be balanced with privacy rights.

**Pseudonymization vs anonymization** — pseudonymized data can be re-identified with a key (still personal data); anonymized data cannot (no longer personal data).

**System of record / authoritative source** — the one system that legally "owns" a piece of data. The population registry owns your name and date of birth; Sahl holds a **copy** and must refresh it, not overwrite it.

**Consent record** — evidence that a citizen agreed (when, to what, which version of the text, for which purpose), and the ability to withdraw.

### What Sahl does

- **PostgreSQL Flexible Server**, Burstable tier in dev; General Purpose + zone-redundant HA defined in the prod Bicep.
- **Private access only** (VNet integration or private endpoint) — no public endpoint.
- **Entra authentication**: the API's managed identity is a Postgres role; no DB password in config.
- **Mock National Registry** as a separate small service with ~1,000 synthetic people (QID-format IDs, Arabic/English names, nationality, family relations, addresses), and companies/properties for Commerce and Municipality.
- **"Tell us once"**: Laravel fetches the citizen profile from the registry (with the citizen's consent), caches a copy, marks fields as **"verified from registry"** vs **"self-declared,"** and refreshes on each login.
- **Consent table**: purpose, version of consent text, timestamp, channel, withdrawal.
- **Documents** (uploaded drawings, generated PDFs) in **Blob Storage**, not the DB; DB holds metadata + hashes.
- **Redis** (Azure Cache for Redis or a container) for cache/sessions/queues as needed ⚠ Decide in build phase — Azure Cache for Redis tiers and the newer Azure Managed Redis have different pricing.
- CMK + column encryption for criminal-record-related fields are part of the **gov profile**.

### What we didn't choose

| Option | Why not |
|---|---|
| Azure SQL Database | Excellent, very common in Microsoft-heavy governments; serverless tier is cheap; **Ledger** tables give tamper-evidence. Lost mainly on Laravel/Postgres familiarity and Postgres features (JSONB, RLS). A strong alternative to mention |
| MySQL Flexible Server | Familiar but fewer learning opportunities (RLS, JSONB richness) |
| Cosmos DB | Great for globally distributed, high-scale, schema-flexible data. Government case management is relational with strong consistency needs; Cosmos would fight us |
| Database per ministry | See Chapter 9 |

### What GCC governments actually do

- **Oracle and Microsoft SQL Server** dominate legacy government estates; PostgreSQL is increasingly adopted for new and open-source-friendly platforms.
- **National registries** (population, address, commercial, land) are the crown jewels — usually on-prem or sovereign, and accessed by other systems via the integration bus, never directly.
- Privacy laws across the GCC (Qatar PDPPL 2016, UAE PDPL 2021, Saudi PDPL in force 2023, Bahrain PDPL 2018, Oman PDPL 2022 — **⚠ Verify** details) push consent, purpose limitation, and data-subject rights into system design.
- **Data exchange agreements** between entities define exactly which fields may be shared for which purpose.

### How to decide yourself

- Relational with strong consistency? → Postgres/SQL. Massive scale + flexible schema + global? → Cosmos.
- Which engine does the team know and the government already license/support?
- What's the classification? Do I need CMK, column encryption, Ledger/tamper evidence?
- Who's authoritative for each field? Am I a copy or the source?
- Where do backups go (residency!)?

### Self-test

- What's PITR and how does it differ from a replica?
- Explain CMK and crypto-shredding.
- Why mark fields as "verified from registry" vs "self-declared"?
- Why is a good-conduct certificate a sensitive-data problem?
- Name two reasons a government might prefer Azure SQL over Postgres.

---

## 11. Integration

### The simple version

Ministries need to talk to each other. They can **phone** (synchronous: you wait for the answer) or **leave a letter in a mailbox** (asynchronous: you continue your day, they reply later). Phones are fast but fail if nobody picks up; letters are reliable but slower. Big systems use both — and need a **reception desk** (API gateway) that checks who's calling.

### The concepts

**Synchronous (request/response)** — HTTP/REST/gRPC. Caller waits. Simple, but creates **temporal coupling**: if the callee is down, the caller fails.

**Asynchronous messaging** — the sender puts a message on a broker; receivers process it later. Decouples availability and absorbs load spikes (**queue-based load leveling**).

**Commands vs events**
- **Command** — "Generate certificate #123." Directed at one handler; expects it to be done.
- **Event** — "Application #123 was approved." A fact that happened; zero or many listeners react.

**Queue vs topic**
- **Queue** — one message, one consumer (competing consumers for scale).
- **Topic + subscriptions** — publish once, every subscription gets a copy (pub/sub).

**Delivery guarantees**
- **At-most-once** — may lose messages.
- **At-least-once** — never loses, may **duplicate** → consumers must be **idempotent**.
- **Exactly-once** — effectively impossible end-to-end across systems; you approximate it with at-least-once + idempotency.

**Dead-letter queue (DLQ)** — where messages go after failing N times, for inspection instead of infinite retries.

**Azure messaging services**

| Service | Model | Best for | Not for |
|---|---|---|---|
| **Service Bus** | Enterprise broker: queues, topics, sessions (ordering), transactions, DLQ, duplicate detection, scheduled messages | Business commands/events where every message matters | Massive telemetry streams |
| **Event Grid** | Lightweight event routing (push), reactive "something happened" notifications, many Azure sources built in | Reacting to Azure events (blob created), fan-out of notifications | Long processing pipelines needing ordering/transactions |
| **Event Hubs** | High-throughput streaming log (Kafka-compatible) | Telemetry, clickstreams, millions of events/sec | Business workflows |
| **Storage Queues** | Simple, cheap queues | Basic background jobs | Pub/sub, ordering, rich features |

**API Gateway / API Management (APIM)** — a front for APIs:
- **Policies**: `validate-jwt`, rate limiting, quotas, IP filtering, header transformation, caching, mocking.
- **Products & subscriptions**: package APIs, give each consumer (e.g., another ministry) a subscription key + quota.
- **Versioning & revisions**.
- **Developer portal**: documentation for consumers.
- **Self-hosted gateway**: run the gateway on-prem/in another cloud with central management.
- Tiers matter a lot: Consumption, Developer (no SLA), Basic/Standard/Premium, and the newer **v2 tiers** (Basic v2, Standard v2, Premium v2) with different networking capabilities and prices **⚠ Verify**.

**Integration patterns**
- **ESB (Enterprise Service Bus)** — older centralized integration hub with transformation and routing logic inside. Many governments built national ESBs in the 2000s–2010s.
- **API-led integration** — modern: each system exposes well-defined APIs through a gateway; logic stays in the systems ("smart endpoints, dumb pipes").
- **Anti-corruption layer** — a translation layer protecting your model from a messy external system's model (e.g., wrapping a legacy registry).

**Transactional outbox** — the problem: you update the DB *and* publish a message; if the app crashes in between, they disagree. Solution: write the message into an `outbox` table **in the same DB transaction**, and a relay publishes it afterwards (at-least-once). Consumers handle duplicates.

**Inbox / idempotent consumer** — consumers record processed message IDs and skip duplicates.

**Saga** — a long business transaction across services, made of local steps with **compensating actions** if a later step fails (e.g., refund if permit issuance fails after payment).

**Correlation ID** — an ID that follows one business flow across HTTP calls and messages, for tracing and audit.

### What Sahl does

- **APIM** in front of **system-to-system APIs** — inter-ministry APIs (Commerce registry, National Registry), the payment webhook endpoint, and any future mobile/public API — modeling a **national integration platform**. (Browser page traffic goes Front Door → Laravel directly; see Chapter 5.)
  - Each consuming system gets an APIM **subscription** with quotas.
  - `validate-jwt` at the gateway; Laravel validates again (defense in depth).
  - Developer-tier for the PoC; Premium/v2 networking in the gov profile.
- **Messaging** (final choice is an open decision): domain events like `ApplicationSubmitted`, `ReviewCompleted`, `PaymentConfirmed`, `CertificateIssued`, published via an **outbox**, consumed by Functions/workers with **idempotency** and **DLQs**.
- **Correlation IDs** on every request/message, also written to the audit trail.

### What we didn't choose (so far)

- **Direct DB access between modules/ministries** — the cardinal sin. Creates invisible coupling and makes audit impossible.
- **A heavy ESB product** — teaches 2008-era integration; API-led + messaging is the modern pattern.
- **Event Hubs / Kafka** — wrong tool: we have business events, not telemetry streams.

### What GCC governments actually do

- **National integration platforms / government service buses** connect ministries (Saudi Arabia's GSB is a well-known example). Data exchanges (e.g., "verify this person's address") are published as services with formal onboarding, agreements, and logging.
- Many are evolving from ESB-style to **API gateways + API marketplaces/developer portals** (open-government-data and API programmes).
- Strong governance: every exchange has an owner, a legal basis, a data-sharing agreement, and audit logs.

### How to decide yourself

- Must the caller **wait** for the answer to continue? (sync) Or is "eventually" fine? (async)
- What happens if the other side is **down for an hour**?
- Can this message be **lost**? Can it be **duplicated**? Does **order** matter?
- Is it a **command** or an **event**? One consumer or many?
- Who outside our system consumes this API, and how do we **govern** them?

### Self-test

- Command vs event: give one of each from Sahl.
- Why can't you get exactly-once delivery, and what do you do instead?
- Walk through the outbox pattern and the failure it prevents.
- Service Bus vs Event Grid: when would you use each?
- What does APIM add that Laravel middleware can't easily do?

---

## 12. Payments

### The simple version

Paying a government fee is like ordering at a busy restaurant through a waiter: you pay at the till (payment gateway), the till sends a note to the kitchen (webhook) saying "table 5 paid." Sometimes the note arrives twice, late, or before the waiter even gets back. The kitchen must not cook the meal twice, and at closing time someone counts the till against the orders (reconciliation).

### The concepts

**Payment gateway / PSP** — the service that takes card/bank payments. Governments often run a **central government payment platform** that ministries integrate with.

**Hosted payment page (redirect)** — the citizen is redirected to the gateway's page to enter card details, then redirected back. Your system **never touches card data** → keeps you out of most **PCI DSS** scope.

**PCI DSS** — card-industry security standard. Touch card numbers → heavy compliance. Hosted pages avoid it.

**Webhook / server-to-server notification** — the gateway calls your endpoint with the payment result. **The redirect back is not proof of payment** (the user can fake or close it); only the **signed webhook** or a server-side status query is.

**Webhook security** — verify **signature (HMAC)** with a shared secret, check **timestamp** (reject old ones → **replay protection**), accept only from known IPs if possible.

**Idempotency** — processing the same webhook twice must have the same effect as once. Implement with a unique constraint on the gateway's transaction ID, and state transitions that ignore duplicates (`paid → paid` is a no-op).

**Idempotency keys** — when *you* call an API that creates something (start a payment), send a unique key so retries don't create duplicates.

**Out-of-order events** — "refunded" may arrive before "paid." Use a **state machine** with valid transitions and timestamps/sequence numbers.

**Reconciliation** — periodic comparison of your records against the gateway's settlement report; mismatches (paid at gateway but "pending" here) are flagged/fixed. Every real finance system has it.

**Fee calculation** — fees per service, maybe per area/size (permits), exemptions (people of determination, certain nationalities, government employees), VAT where applicable (⚠ GCC VAT rules differ by country; Qatar has not implemented VAT as of last check — Verify).

### What Sahl does

- **Mock payment gateway** — a separate small app: hosted payment page, configurable chaos (delay webhooks, send duplicates, send out of order, fail signatures), settlement report endpoint.
- **Payments module** in Laravel: payment state machine, signed webhook endpoint (through APIM), idempotency on gateway transaction ID, outbox event `PaymentConfirmed`.
- **Nightly reconciliation** job → mismatch report in back-office.
- Fees configured per service in the service catalog.

### What GCC governments actually do

- **Central government payment systems** collect fees for all entities and settle to the treasury (e.g., Saudi SADAD, UAE federal/emirate payment hubs). Ministries integrate once with the central system.
- Payments are tied to **bill numbers / payment references** generated by the ministry; the citizen can pay via portal, banking app, or kiosk — so the "payment" can arrive from channels you don't control. That's another reason webhooks/notifications, not redirects, are the source of truth.

### Self-test

- Why isn't the redirect back to your site proof of payment?
- How do you make webhook processing idempotent?
- What's reconciliation and what kind of bug does it catch?
- How does a hosted payment page reduce PCI scope?

---

## 13. Workflows

### The simple version

A building permit is like a file folder that travels around the ministry: Planning, Civil Defense, and Electricity each stamp it (in parallel), it may get sent back to the citizen for corrections, then the citizen pays, then a manager signs. Some of these steps take days. The system must **remember where every folder is**, even if servers restart, and **nag people** who sit on folders too long.

### The concepts

**State machine** — a model with **states** (Submitted, UnderReview, CorrectionsRequested, AwaitingPayment, Approved, Rejected, Issued) and **allowed transitions** with guards ("can only approve if all reviews passed and payment confirmed").

**Long-running process** — a process that spans hours/days and waits for humans or external events.

**Orchestration vs choreography**
- **Orchestration** — a central coordinator tells each participant what to do next. Easy to see and change the process; the coordinator is a central dependency.
- **Choreography** — each participant reacts to events and emits new ones; no central brain. Loosely coupled but hard to see "where is permit #123 and why?"

**Fan-out / fan-in** — start parallel tasks (3 reviews) and wait for all to complete.

**Human task** — a step completed by a person (approve/reject), with assignment, claiming, deadlines.

**SLA timer / escalation** — "if not reviewed within 5 working days, notify supervisor; at 10 days, reassign." **Working days** in GCC countries: weekend is Friday–Saturday in most GCC countries (some differ ⚠ Verify; e.g., UAE moved to Saturday–Sunday in 2022), plus public holidays — including Islamic holidays that follow the lunar calendar and are announced close to the date.

**Durable execution** — workflow state is persisted after every step, so code can "wait 5 days" without a process running for 5 days, and resumes after crashes.

**Options on Azure**

| Option | What it is | Strengths | Weaknesses |
|---|---|---|---|
| **In-app state machine** (Laravel + DB + scheduler) | Status column + transitions + scheduled jobs | Simple, all in one codebase, easy queries | You build timers, retries, fan-in yourself |
| **Durable Functions** | Code-first orchestrations (C#, JS/TS, Python, Java, PowerShell) with durable timers, fan-out/in, external events (human approval) | Very expressive, testable code, cheap | Determinism rules for orchestrator code; another language/runtime besides PHP |
| **Logic Apps (Standard)** | Low-code visual workflows with hundreds of connectors | Visual, business-readable, quick integrations | Harder to version/test like code, costs per action, complex logic gets messy |
| **BPM engine** (Camunda, etc.) | BPMN-modeled processes, human-task management | Business analysts can read BPMN; strong human-task features | Another platform to run |

### What Sahl does (partly open decision)

- **Permit lifecycle state** lives in Laravel (the source of truth for what the citizen sees).
- **Orchestration engine** (open decision: **Durable Functions** vs **Logic Apps**) drives the multi-step process: auto-validation → parallel reviews → correction loop → payment wait → approval → issue → signed permit.
- **SLA timers** with escalation, using a **working-day calendar** that includes the fictional country's weekend and holidays.
- Every transition → **audit event** + domain event.

### What GCC governments actually do

- Heavy use of **BPM and case-management products** (and Microsoft Dynamics/Power Platform in Microsoft-centric entities) for approvals.
- **SLAs are published to citizens** ("this service takes 5 working days") and monitored by leadership dashboards; performance against SLA is a government KPI. Some countries publicly rate entities on service delivery.
- Multi-entity approvals (e.g., building permits needing civil defense and utilities clearance) are classic and often handled through the integration platform, with each entity's decision fed back to the lead entity.

### How to decide yourself

- How long does the process wait? Must it survive restarts/deployments mid-flight?
- Who needs to **read/change** the process — developers or business analysts?
- How many processes and how often do they change?
- How will you **version** workflows when 300 permits are mid-flight on the old version?
- Where is the **single source of truth** for status shown to the citizen?

### Self-test

- Orchestration vs choreography: pros and cons.
- What's a durable timer and why can't you use `sleep()`?
- How do you handle a workflow definition change while instances are in flight?
- Why do working-day calendars in the GCC need a yearly update?

---

## 14. Documents and trust

### The simple version

A paper certificate is trusted because of the stamp and signature — and because forging them is hard. A digital certificate needs the same thing: a **mathematical stamp** that only the ministry can produce and anyone can check. The QR code is a shortcut that lets a bank teller check it with their phone in 3 seconds.

### The concepts

**Hash** — a fixed-length fingerprint of data (SHA-256). Change one byte of the PDF → completely different hash.

**Asymmetric cryptography** — a key pair: **private key** (kept secret, used to **sign**) and **public key** (shared, used to **verify**).

**Digital signature** — sign the document's hash with the private key. Anyone with the public key can verify: (1) **authenticity** — it came from the key holder, (2) **integrity** — it wasn't changed.

**Non-repudiation** — the signer can't credibly deny signing, because only they hold the private key (which is why protecting it is everything).

**Certificate (X.509)** — a document binding a public key to an identity ("this key belongs to the Ministry of Interior"), itself signed by a **Certificate Authority (CA)**.

**PKI (Public Key Infrastructure)** — the whole system of CAs, certificates, revocation lists (CRL) / OCSP, policies. A **chain of trust** goes from your certificate → intermediate CA → **root CA** that verifiers trust.

**National PKI** — most GCC countries run a national PKI: national root CA, CAs issuing certificates to government entities, and often to citizens (on ID cards or in mobile identity apps) for legally recognized e-signatures.

**PDF signatures (PAdES)** — the standard for embedding signatures in PDFs so Adobe Reader shows "Signed and all signatures are valid." Includes **timestamping** (a trusted time authority proves *when* it was signed) and **LTV** (long-term validation: embedding revocation info so the signature can be verified years later).

**HSM (Hardware Security Module)** — tamper-resistant hardware that generates and uses keys **without ever exporting them**. FIPS 140-2/140-3 Level 3 is the common government bar.

**Azure Key Vault** — managed service for **secrets**, **keys**, and **certificates**.
- **Standard** — software-protected keys.
- **Premium** — HSM-protected keys.
- **Managed HSM** — dedicated, single-tenant, FIPS 140-3 Level 3 HSM pool, customer-controlled security domain. (Expensive.)
- Key operations (`sign`, `verify`, `wrapKey`) happen **inside** the vault; the app sends a hash, gets back a signature.
- **Soft delete + purge protection** — deleted keys are recoverable for a period and cannot be permanently purged early. Mandatory for anything important.
- **RBAC data-plane roles** (e.g., `Key Vault Crypto User`) instead of legacy access policies.

**QR verification** — the QR encodes a URL like `https://verify.sahl.example/c/{documentId}?h={shortHash}`. The page shows the issuing entity, holder (minimal data!), issue/expiry date, status (valid/revoked), and whether the hash matches. **Privacy**: show the minimum; anyone scanning shouldn't see the full record.

**Revocation** — a certificate can be revoked (issued in error, superseded). Verification must check current status, not just the signature.

### What Sahl does

- **Functions service** generates PDFs (bilingual, RTL Arabic layout), computes the hash, calls **Key Vault `sign`** with the ministry's key, embeds the signature (PAdES where library support allows), stores the PDF in **Blob Storage** (immutable container), and records metadata + hash in the DB.
- **Key per entity** (`kv-…/keys/moi-signing`, `mom-signing`, …) so a compromise of one ministry's key doesn't affect others.
- **Public verification page** with minimal disclosure, plus a revocation flag.
- Dev: Key Vault **Standard** with a self-signed "Sahl Fictional Root CA" chain. Gov profile: **Premium (HSM-backed)** keys; Managed HSM documented as the production choice.

### What we didn't choose

- **Signing in the app with a key file** — the private key would sit in app config/disk; any app compromise = key theft. Never.
- **Managed HSM in the PoC** — correct for production, too expensive for credits.
- **Using a real CA** — the PoC is fictional; a self-built CA chain teaches PKI just as well.

### What GCC governments actually do

- Government-issued documents increasingly carry **QR codes for verification** and are digitally signed by entity certificates under the **national PKI**.
- Citizen e-signatures for legal acts use national-ID-based certificates, often through the national identity mobile app.
- Keys for national PKI are held in **HSMs in government facilities**; cloud-hosted signing keys for sensitive documents generally require HSM protection and in-country residency.

### Self-test

- What do a hash and a signature each prove?
- Why does the private key never leave Key Vault, and how does signing still work?
- What is purge protection and why must it be on?
- What should a public verification page *not* show?
- Why is revocation checking necessary even if the signature is valid?

---

## 15. Networking

### The simple version

Picture a government compound:
- One **guarded gate** facing the street (Front Door + WAF). Nobody climbs the walls.
- Inside, separate **buildings** for different purposes (subnets), with **internal doors** that only open for specific badges (NSGs).
- The filing room (database) has **no street door at all** — you can only reach it from inside the compound (private endpoints).
- All outgoing mail goes through a **post office** that checks where it's going (Azure Firewall).
- A **central hub** building holds shared services; each department's building connects to the hub (hub-spoke).

### The concepts — from zero

**IP address & CIDR** — `10.10.0.0/16` = a range of 65,536 addresses; `/24` = 256 addresses. You plan non-overlapping ranges for every network you'll ever connect (including on-prem!). **IP address planning is an architecture decision** — overlapping ranges can't be peered.

**VNet (Virtual Network)** — your private network in Azure, in one region.

**Subnet** — a slice of a VNet. Some services need a **dedicated/delegated subnet** (Postgres VNet integration, Container Apps environment, App Gateway, Azure Firewall needs one named `AzureFirewallSubnet`, Bastion `AzureBastionSubnet`).

**NSG (Network Security Group)** — stateful allow/deny rules (source, destination, port) on subnets/NICs. Layer 3/4 only.

**ASG (Application Security Group)** — group VMs/NICs logically to write NSG rules like "web → db" instead of IPs.

**Route table (UDR — User-Defined Route)** — override default routing, e.g., send `0.0.0.0/0` (all internet traffic) to the firewall.

**VNet peering** — connect two VNets privately over Microsoft's backbone. **Not transitive**: if A↔Hub and B↔Hub, A can't reach B unless the hub routes it (via firewall/NVA).

**Hub-spoke topology** — a central **hub VNet** (firewall, Bastion, DNS resolver, gateways to on-prem) peered with **spoke VNets** (one per workload/environment). Centralizes security and shared services. **Azure Virtual WAN** is Microsoft's managed version for large estates.

**Public endpoint vs Service endpoint vs Private endpoint**
- **Public endpoint** — the PaaS service is reachable on the internet (optionally IP-restricted).
- **Service endpoint** — your subnet's traffic reaches the service over the Azure backbone and the service can restrict access to that subnet, **but the service keeps its public IP**.
- **Private endpoint (Private Link)** — the service gets a **private IP in your subnet**. Combine with **"public network access: disabled"** → the service is unreachable from the internet. This is the government standard.

**Private DNS zones** — when you call `sahlkv.vault.azure.net`, DNS must return the **private IP** inside your network. Azure uses zones like `privatelink.vaultcore.azure.net`, linked to your VNets. **The #1 cause of "private endpoint doesn't work" is DNS.** In hub-spoke, private DNS zones are centralized in the hub, and **Azure DNS Private Resolver** handles on-prem ↔ Azure resolution.

**Ingress vs egress**
- **Ingress** — traffic coming in (citizens → portal).
- **Egress** — traffic going out (our app → payment gateway, → national IdP). **Egress control** prevents compromised code from sending data anywhere ("data exfiltration"). Done with Azure Firewall (FQDN allow-lists) + UDRs.

**Azure Firewall** — managed stateful firewall: network rules, application (FQDN) rules, threat-intelligence filtering; **Premium** adds TLS inspection and IDPS. Tiers: Basic, Standard, Premium (prices differ a lot ⚠ Verify).

**WAF (Web Application Firewall)** — layer-7 protection against OWASP Top 10 attacks (SQLi, XSS), bots, with managed rule sets. Runs on **Front Door** or **Application Gateway**.

**Azure Front Door** — global edge: anycast entry close to users, TLS termination, caching (CDN), routing, WAF. **Premium** can connect to origins via **Private Link**, so your origin has no public endpoint.

**Application Gateway** — regional layer-7 load balancer with WAF v2, deployable inside your VNet.

**DDoS Protection** — Azure has basic infrastructure protection for everyone; **DDoS Network Protection** (paid, expensive) adds adaptive tuning, telemetry, and cost protection for public IPs in a VNet.

**Azure Bastion** — browser-based RDP/SSH to VMs without public IPs on the VMs.

**ExpressRoute / VPN Gateway** — private connectivity to on-prem datacenters (ExpressRoute: dedicated circuit via a provider; VPN: encrypted over the internet).

**Zero Trust networking** — network location isn't trust. Even inside the VNet, every call is authenticated (managed identity, tokens). Networks are a **second** wall, not the only one.

**Landing zone** (Cloud Adoption Framework) — the pre-built, policy-governed foundation (management groups, subscriptions, hub network, logging, policies) that workloads deploy into. **Platform landing zone** (shared: connectivity, identity, management) vs **application landing zone** (your workload's subscription).

### What Sahl does — two profiles

**Lean profile (day-to-day, credits-friendly):**
```
Internet
   │
   ▼
[Front Door Standard + WAF] → Laravel "citizen" surface (public ingress)
[APIM Developer (or Consumption)]  → system-to-system APIs (registries, payment webhooks)
   "backoffice" surface: internal-only ingress (lean profile: IP-restricted for you; gov profile: VPN/Bastion only)
   │
   ▼  VNet: vnet-sahl-dev-gwc (10.20.0.0/16)
   ├── snet-apps        → Laravel citizen + backoffice surfaces + workers (VNet integrated)
   ├── snet-functions   → Functions (Flex Consumption, VNet integrated)
   ├── snet-pe          → Private endpoints: Key Vault, Storage, Service Bus*
   └── snet-postgres    → Postgres Flexible (delegated) or PE
   Private DNS zones linked to the VNet. No firewall; NSGs only.
   (*Service Bus Premium is required for private endpoints — lean profile may keep Standard tier with public endpoint + RBAC; a documented exception. ⚠ Verify)
```

**Gov profile (deployed in bursts for demos):**
```
Internet
   │
   ▼
[Front Door Premium + WAF]  ──Private Link──►  [Laravel citizen surface (private)]
Partner systems ──► [APIM (internal/VNet)] ──► registries / webhooks
Employees ──VPN / Bastion──► [Laravel backoffice surface (internal-only)]
                                                    │
Hub VNet (10.0.0.0/16)                              ▼
 ├── AzureFirewallSubnet  → Azure Firewall (egress allow-list: national IdP, payment gateway, registries)
 ├── AzureBastionSubnet   → Bastion
 ├── snet-dns-resolver    → DNS Private Resolver
 └── Private DNS zones (central)
        ▲ peering                       ▲ peering
Spoke: sahl-prod (10.30.0.0/16)    Spoke: shared-services (mock national systems)
 ├── apps, functions, PEs, postgres
 └── UDR 0.0.0.0/0 → Firewall
```

All of it lives in **Bicep** behind a `profile` parameter (`lean` | `gov`), so the *same code* produces both.

### What we didn't choose

- **Everything public with IP restrictions** — cheap, but violates the core government principle "no public endpoints except the front door."
- **Gov profile always on** — real but would burn the credits in days.
- **Virtual WAN** — great for large multi-region estates; overkill for one workload.

### What GCC governments actually do

- **Government networks** (private networks connecting ministries) are common; cloud workloads connect to them via ExpressRoute or equivalents.
- Cybersecurity frameworks require **network segmentation**, **perimeter protection (WAF/firewalls)**, **egress control**, and **no direct internet exposure of data stores**.
- Large entities build **landing zones** following Microsoft CAF / Azure Landing Zone reference architectures, often with "**Sovereign Landing Zone**" variants that add policies for data location and confidential computing ⚠ Verify current naming.

### How to decide yourself

- What must be reachable from the internet? (Ideally exactly one entry point.)
- What do my workloads need to reach **outbound**, and can I allow-list it?
- Which PaaS services need private endpoints, and what **SKU** does that require?
- Where does DNS resolution happen, and who manages private zones?
- Will this ever connect to **on-prem**? Plan IP ranges now.

### Self-test

- Service endpoint vs private endpoint — what's the key difference?
- Why is VNet peering non-transitive and what does hub-spoke do about it?
- Explain why DNS is the #1 private-endpoint problem.
- What's egress control and what attack does it limit?
- Front Door vs Application Gateway — when would you choose each?

---

## 16. The AI assistant

### The simple version

The assistant is a librarian who has read every official service guide. When a citizen asks "how do I get a building permit for an extension?", the librarian **finds the right pages first**, then answers **only from those pages**, and shows which pages it used. It never looks at anyone's personal file.

### The concepts

**LLM (Large Language Model)** — a model that generates text (GPT-family via Azure OpenAI / Azure AI Foundry, and others).

**Hallucination** — the model confidently states something untrue. In government this is a serious risk ("the fee is 200" when it's 500).

**RAG (Retrieval-Augmented Generation)**
1. **Ingest**: split documents into **chunks**, compute **embeddings** (vectors representing meaning), store in an index.
2. **Retrieve**: embed the question, find the most similar chunks (vector search), often combined with keyword search (**hybrid search**) and **semantic re-ranking**.
3. **Generate**: give the model the question + retrieved chunks + instructions ("answer only from these sources, cite them, say 'I don't know' otherwise").

**Embeddings** — numeric vectors; similar meaning → nearby vectors. Allows "building license" to match "construction permit," and Arabic question → Arabic/English documents with multilingual embedding models.

**Azure AI Search** — the index: keyword (BM25), vector, hybrid, semantic ranker, Arabic language analyzers.

**Grounding & citations** — answers tied to retrieved sources, with links.

**System prompt / guardrails** — instructions and filters: stay on topic, no legal advice, escalate to a human.

**Content safety** — Azure AI Content Safety filters harmful input/output; **prompt shields** detect jailbreak/injection attempts.

**Prompt injection** — malicious text (in the question or in retrieved documents) trying to override instructions. Risk grows with what the model can *do* (tools) and *see* (private data). Our assistant has **no tools and no personal data** → small blast radius. That's a deliberate design decision.

**Model and data residency** — not every model is available in every region; deployments can be **regional**, **data zone**, or **global** (requests may be processed in other regions). For a government, that matters as much as database location. **⚠ Verify** model availability in Qatar Central/UAE regions at production time.

**Evaluation** — test sets of questions with expected answers; measure groundedness, relevance, correctness — in **both languages**.

**Responsible AI** — transparency ("you're talking to an AI"), human fallback, monitoring, bias checks (Arabic dialects vs MSA, English proficiency).

### What Sahl does

- **Corpus**: the fictional ministries' public service guides (Arabic + English): requirements, fees, steps, SLAs, FAQs.
- **Azure AI Search** (free/basic tier) with hybrid search; **Azure OpenAI** (via AI Foundry) for embeddings and chat, **regional** deployment where available.
- **No personal data** in prompts; no tools; no login required (or anonymous within the portal).
- Answers **with citations** to service guide pages; "I don't know — contact the service center" fallback.
- Content safety filters on; logs of questions (no PII) to improve the corpus.
- An **evaluation set** (~50 questions per language).

### What we didn't choose

- **"Check my application status" via tools** — impressive but adds authorization through the model, prompt-injection risk on private data, and a much bigger threat model. Documented as Phase 2 with the required controls (OBO tokens, tool allow-lists, output filtering).
- **Fine-tuning** — not needed; RAG handles changing content better (fees change; re-index instead of re-train).

### What GCC governments actually do

- Several GCC governments have launched **generative-AI assistants** for government services and publish national AI strategies and ethics principles (e.g., Saudi SDAIA's AI ethics principles, UAE's AI strategy and government AI assistants, Qatar's national AI strategy) **⚠ Verify** specifics.
- Concerns center on **data residency of AI processing**, **Arabic quality** (dialects), and **accountability** for wrong answers — leading to RAG over official content, disclaimers, and human escalation.

### Self-test

- Walk through RAG's three phases.
- Why does giving the assistant no personal data and no tools reduce risk so much?
- What's the difference between a regional and a global model deployment, and why does a government care?
- How would you evaluate whether the Arabic answers are good?

---

## 17. Audit, logging and observability

### The simple version

- **Logs** are the diary: "at 10:02, X happened."
- **Metrics** are the dashboard gauges: "300 requests/minute, 2% errors."
- **Traces** are the parcel tracking number: one ID that follows a single request through every system it touched.
- **The audit trail** is different from all three: it's the **legal record** of who did what to whose data. Ordinary logs help engineers; the audit trail protects citizens and holds employees accountable. It must be **impossible to edit or delete**, even for admins.

### The concepts

**Observability** — being able to answer *new* questions about the system's behavior from its outputs (logs, metrics, traces), not just the ones you predicted.

**Azure Monitor** — the umbrella: metrics, logs, alerts, workbooks.
- **Log Analytics workspace** — the log database; queried with **KQL (Kusto Query Language)**.
- **Application Insights** — APM (Application Performance Monitoring) for apps: requests, dependencies, exceptions, traces, availability tests, live metrics. Stores data in Log Analytics (workspace-based).
- **Diagnostic settings** — route Azure resources' own logs (Key Vault access, Postgres logs, APIM gateway logs, Front Door/WAF logs) to Log Analytics/Storage.
- **Activity Log** — control-plane events: who created/changed/deleted Azure resources.
- **Alerts** — metric or log query conditions → **action groups** (email, SMS, webhook).
- **Workbooks / dashboards** — interactive reports.

**OpenTelemetry (OTel)** — the vendor-neutral standard for traces/metrics/logs. Azure Monitor supports it (Azure Monitor OpenTelemetry Distro). PHP and browser OTel SDKs exist.

**Distributed tracing** — **W3C Trace Context** headers (`traceparent`) carry a trace ID across HTTP calls; for messages, put it in message properties.

**Structured logging** — JSON logs with fields (`trace_id`, `entity_id`, `user_id_hash`) instead of free text. Searchable.

**PII in logs** — never log full QIDs, names, tokens, or document contents in operational logs. Mask or hash. Logs are copied widely and kept long — they are a classic data-leak path.

**Audit trail requirements**
- **Who** (user ID, role, entity, acting-on-behalf-of)
- **What** (action, resource type + ID, before/after for changes when appropriate)
- **When** (UTC timestamp, from a trusted clock)
- **Where** (IP, client app, correlation ID)
- **Why** (justification for sensitive access, e.g., "viewing criminal record for application #123")
- **Outcome** (success/denied)

**Tamper evidence vs tamper resistance**
- **Tamper-resistant** — can't be changed (WORM storage).
- **Tamper-evident** — changes are detectable (hash chains: each record includes the hash of the previous one; Azure SQL Ledger does this).
- Best: both.

**WORM / immutable Blob Storage**
- **Time-based retention policy** — blobs can't be modified/deleted until retention expires.
- **Legal hold** — can't be deleted until the hold is removed (investigations).
- **Locked policy** — once locked, retention can't be shortened, **not even by the subscription owner or Microsoft support**. That's what makes it legally meaningful.
- **Version-level immutability** — per-blob-version policies.
- **Access tiers**: Hot → Cool → Cold → **Archive** (cheapest, hours to rehydrate) via **lifecycle management** rules.

**Access logging ("who viewed")** — in government, *reading* a citizen's record is also audited, not just changes. "Why did this employee look at the minister's file?" is a real investigation pattern.

### What Sahl does

- **App Insights** (workspace-based) for both Laravel surfaces (server + browser telemetry), Functions, and mocks, with OpenTelemetry where possible; **correlation IDs** end-to-end.
- **Log Analytics** receives diagnostic logs from Key Vault, Postgres, APIM, Front Door/WAF, Service Bus, Storage.
- **Dashboards & alerts**: error rate, latency, DLQ depth, failed webhooks, SLA breaches, Key Vault access anomalies.
- **Audit module** in Laravel: every employee read/write on citizen data → an audit event → written to the DB (for in-app search by Auditors) **and** to an **immutable Blob container** (append blobs / JSON lines, time-based retention, **locked** in the gov profile; unlocked in dev so we can clean up).
- **Hash chain** on audit records for tamper evidence; a verification command recomputes the chain.
- **Lifecycle policy** moves audit blobs to Cool/Archive after N days.
- PII masking in operational logs.

### What we didn't choose

- **Audit only in the application DB** — DBAs and admins could edit it; not credible.
- **Microsoft Sentinel / Defender** (for now) — valuable (SIEM correlation, threat detection); deferred for budget. Documented as the production SOC integration.

### What GCC governments actually do

- Government entities send security logs to a **SOC (Security Operations Center)**, often a national or sector SOC, using a **SIEM** (Sentinel, Splunk, QRadar…). Frameworks require defined **log retention** periods and protection of logs from tampering.
- Access to citizen records by employees is audited and periodically reviewed; misuse leads to disciplinary/legal action.

### Self-test

- How is an audit trail different from application logs?
- What makes a locked immutability policy legally meaningful?
- Tamper-resistant vs tamper-evident — define both and give an implementation of each.
- Why audit *reads*, not just writes?
- What must never appear in logs?

---

## 18. Security and compliance

### The simple version

**Security** is locking the doors. **Compliance** is being able to **prove** to an inspector that you locked the doors, that you check them every day, and that you know who has keys. Government systems need both — a secure system that can't prove it is non-compliant, and a compliant-on-paper system that isn't secure is a disaster waiting to happen.

### 18.1 The frameworks (simplified, ⚠ Verify current versions before quoting)

**Qatar (our scenario)**
- **National Information Assurance (NIA) Policy** — Qatar's national information-security standard for government and critical-sector entities, maintained by the **National Cyber Security Agency (NCSA)**. Covers governance, risk management, **data classification**, access control, cryptography, logging & monitoring, incident management, business continuity, third-party/cloud security. There is a related compliance/certification scheme.
- **Personal Data Privacy Protection Law (PDPPL), Law No. 13 of 2016** — Qatar's data-protection law, with guidelines from the national data-privacy regulator. Core ideas: lawful, transparent processing; purpose limitation; individuals' rights (access, correction, objection, deletion in certain cases); security safeguards; breach notification; special protection for sensitive ("special nature") data; rules on cross-border transfer.
- **Cloud and data-classification policies** — rules for what classification of government data may be hosted where, and which cloud services are approved.

**Neighbors (useful for interviews — the patterns are similar)**
| Country | Cybersecurity | Privacy | Notes |
|---|---|---|---|
| Saudi Arabia | NCA **ECC** (Essential Cybersecurity Controls), **CCC** (Cloud Cybersecurity Controls), DCC (Data Cybersecurity Controls) | **PDPL** (SDAIA) | Strong in-kingdom requirements for government data |
| UAE | TDRA **Information Assurance Standards**; Dubai **ISR** (DESC) and DESC CSP standard; Abu Dhabi standards | **PDPL** (Federal Decree-Law 45/2021); free-zone regimes (DIFC, ADGM) | Emirate-level rules add to federal |
| Bahrain | National cybersecurity center policies | **PDPL** (Law 30/2018) | Cloud-first, AWS region |
| Oman | National cybersecurity standards | **PDPL** (2022) | |
| Kuwait | CITRA regulations | CITRA data-privacy regulation | |

**International frameworks often referenced alongside** — ISO/IEC 27001 (ISMS), ISO 27017/27018 (cloud security/privacy), ISO 22301 (business continuity), NIST CSF, CIS Benchmarks, OWASP ASVS. Azure publishes its certifications in the **Service Trust Portal**.

### 18.2 The core strategies, explained

**Shared responsibility model**

| Layer | IaaS | PaaS | SaaS |
|---|---|---|---|
| Physical datacenter | Microsoft | Microsoft | Microsoft |
| Network infrastructure, hosts | Microsoft | Microsoft | Microsoft |
| OS & runtime | **You** | Microsoft | Microsoft |
| Application | **You** | **You** | Microsoft |
| Identity & access, data, configuration | **You** | **You** | **You** |

**You are always responsible for identity, data, and configuration.** Most cloud breaches are misconfigurations (public storage, leaked keys, over-privileged identities) — not Microsoft's datacenter failing. In a compliance mapping, you inherit Microsoft's certifications for the bottom layers and must evidence your own controls for the top.

**Data classification** — the foundation of almost every GCC framework. Typical (generic) levels:

| Level (generic) | Example in Sahl | Typical handling |
|---|---|---|
| Public | Service guides, fees | Anyone; integrity matters |
| Internal | Internal procedures, staff directory | Employees only |
| Confidential / Restricted | Citizen profiles, applications, permit drawings | Need-to-know, encryption, residency, audit |
| Highly restricted / Secret | Criminal-record data, security clearances | Strongest controls; may be barred from public cloud |

(Each country names its levels differently — check the official scheme.)

**Defense in depth** — layered controls so one failure isn't a breach: edge (WAF) → gateway (APIM) → app (authZ) → data (private network, encryption, DB roles) → monitoring (alerts).

**Zero Trust** — Microsoft's three principles: **verify explicitly** (always authenticate/authorize with all signals), **use least-privilege access** (JIT/JEA, risk-based), **assume breach** (segment, encrypt, monitor, minimize blast radius).

**Identity is the perimeter** — in cloud, network boundaries blur; identity (Entra, managed identities, Conditional Access) becomes the primary control.

**Secretless architecture** — managed identities, workload identity federation (GitHub OIDC), Key Vault for the remainder, secret scanning in repos.

**Encryption everywhere** — TLS 1.2+ in transit, encryption at rest by default, CMK + HSM for sensitive data in production, key rotation.

**Privacy by design and by default** — privacy built into architecture, not added later; the most privacy-protective setting is the default. In Sahl: consent records, minimal disclosure on verification pages, no PII in AI or logs, purpose-bound cross-ministry access.

**Data minimization & purpose limitation** — only the fields a service needs; only used for that service.

**Data subject rights** — ability to give a citizen their data (export), correct it (via the authoritative source), and explain processing. Design for it: you can't export what you can't find, so data must be traceable to a person.

**Breach readiness** — incident response plan, logs sufficient to investigate, ability to notify within the legal timeframe.

**Least privilege & separation of duties** — Chapter 8; also for Azure itself: developers don't have prod write access; deployments only via pipeline; PIM for emergency access.

**Secure SDLC / DevSecOps** — threat modeling at design, code review, **SAST** (static analysis), **SCA** (dependency/vulnerability scanning — Dependabot, `composer audit`, `npm audit`), **secret scanning**, **IaC scanning** (e.g., PSRule for Azure, Checkov), container image scanning, **DAST** (dynamic testing like OWASP ZAP) against dev.

**Compliance as code** — express rules as **Azure Policy**:
- Allowed locations (residency!).
- Deny public network access on Storage/Key Vault/Postgres.
- Require diagnostic settings to Log Analytics.
- Require tags (`data-classification`, `owner`, `environment`).
- Require TLS 1.2 minimum, HTTPS only.
Assigned at a **management group** or subscription; **Deny** effects prevent violations, **Audit** effects report them, **DeployIfNotExists** auto-remediates. **Regulatory compliance initiatives** in Defender for Cloud map policies to frameworks (some GCC frameworks have built-in initiatives — e.g., Saudi NCA, **⚠ Verify** availability for Qatar NIA).

**Threat modeling (STRIDE)**

| Threat | Means | Sahl example | Mitigation |
|---|---|---|---|
| **S**poofing | Pretending to be someone | Forged token, fake payment webhook | JWT validation (iss/aud/sig), HMAC webhook signatures |
| **T**ampering | Changing data | Editing an issued certificate | Digital signatures, hashes, immutable storage |
| **R**epudiation | Denying an action | "I never approved that permit" | Audit trail with strong identity, WORM, hash chain |
| **I**nformation disclosure | Data leak | Reviewer reads another ministry's files; PII in logs | Entity-scoped authZ, RLS, private endpoints, log masking |
| **D**enial of service | Making it unavailable | Bot floods the appointment booking | WAF, rate limits (APIM), queue-based load leveling |
| **E**levation of privilege | Gaining rights you shouldn't have | Citizen reaches back-office routes; reviewer self-approves | Separate surfaces + ingress, separate tenants/guards, role checks, SoD |

**Risk register** — each threat gets likelihood × impact, owner, mitigation, residual risk, and acceptance. Architects **accept** some risks explicitly (e.g., "back-office reachable by IP allow-list instead of VPN in the lean profile — accepted for PoC").

**Control mapping** — a table: *framework control → how Sahl satisfies it → evidence (screenshot, policy, config, log query)*. Evidence is what auditors want.

### 18.3 How Sahl implements it (summary)

| Strategy | Sahl implementation |
|---|---|
| Residency | ADR-001; prod Qatar Central; Azure Policy allowed-locations (recommended) |
| Classification | Tag every resource and table with a classification; design controls per level |
| Identity | Federation with national IdP mock, Entra workforce + Conditional Access, app roles, PIM (documented) |
| Least privilege | Narrow RBAC for managed identities; entity-scoped policies |
| SoD | Reviewer ≠ approver; auditors read-only; deploy only via pipeline |
| Secretless | Managed identities, GitHub OIDC, Key Vault |
| Encryption | TLS, platform encryption, CMK + HSM in gov profile |
| Network | Private endpoints, single entry point, firewall egress allow-list (gov profile) |
| Audit | Immutable, hash-chained audit trail including reads |
| Privacy | Consent, minimization, no PII to AI/logs, minimal-disclosure verification |
| Secure SDLC | STRIDE model, dependency + secret + IaC scanning in CI |
| Evidence | Control-mapping document against NIA/PDPPL themes |

### 18.4 Recommended addition: Azure Policy

You didn't select it earlier, but it's **free**, directly supports the residency story, and is a favorite architect interview topic ("how do you *enforce* standards across 50 teams?" → policy at management-group scope). Recommended for Step 3 (Azure foundation). Your call.

### Self-test

- In PaaS, which layers are always your responsibility?
- Why does nearly every GCC framework start with data classification?
- State Zero Trust's three principles and give a Sahl example of each.
- Run STRIDE on the payment webhook endpoint.
- What's the difference between Azure Policy `Deny`, `Audit`, and `DeployIfNotExists`?
- What evidence would you show an auditor that citizen data never leaves the approved region?

---

## 19. Reliability

### The simple version

Reliability is planning for things breaking — because they will. How long can the citizen portal be down before it's a news story? How much data can we lose? How fast must we recover? The answers cost money, and the cost rises steeply with each "nine."

### The concepts

**SLA (Service Level Agreement)** — a contractual promise (Azure's per-service SLAs, e.g., 99.95%).
**SLO (Service Level Objective)** — your internal target (e.g., 99.5% of requests succeed under 2 s).
**SLI (Service Level Indicator)** — the actual measurement.

**The nines**

| Availability | Downtime per month (approx.) |
|---|---|
| 99% | ~7.3 hours |
| 99.9% | ~43.8 minutes |
| 99.95% | ~21.9 minutes |
| 99.99% | ~4.4 minutes |

**Composite SLA** — dependencies in series multiply: Front Door (99.99%) × APIM (99.95%) × App (99.95%) × Postgres (99.99%) ≈ **99.88%** — lower than any single part. Redundancy (parallel) raises it.

**RPO (Recovery Point Objective)** — max acceptable **data loss** (time). "RPO 15 min" = we may lose the last 15 minutes of data.
**RTO (Recovery Time Objective)** — max acceptable **downtime** to recover.

**HA vs DR**
- **High availability** — survive *component/zone* failures automatically, within a region (zone-redundant services).
- **Disaster recovery** — recover from *region-level* disasters, usually to another region; often manual or semi-automatic.

**DR strategies (cheapest → most expensive)**: backup & restore → pilot light → warm standby → active-passive → active-active.

**Resilience patterns** — retries with exponential backoff + jitter, timeouts, circuit breakers, bulkheads, queue-based load leveling, graceful degradation ("payments are temporarily unavailable; your application is saved"), health probes.

**Chaos engineering** — deliberately injecting failures to prove resilience (Azure Chaos Studio). Our mock payment gateway's chaos mode is a mini version.

### What Sahl does

- **Target (on paper)**: 99.9% portal availability, RPO 15 min, RTO 4 h for production.
- **HA**: zone-redundant SKUs in the gov/prod profile where supported (Postgres zone-redundant HA, zone-redundant Storage ZRS, APIM/compute zones).
- **DR**: in-country only by default (zones). Cross-region DR to another country documented as **requiring a formal residency exception** — the classic GCC trade-off.
- **Backups**: Postgres PITR, Blob soft delete + versioning, Key Vault soft delete + purge protection.
- **Resilience**: retries/timeouts on outbound calls, DLQs, graceful degradation when national IdP/payment gateway are down.
- **PoC**: single-zone, cheapest SKUs, no SLA expectations — stated honestly.

### What GCC governments actually do

- Government entities maintain **business continuity plans** (often aligned with ISO 22301) and classify services by criticality.
- With only one in-country region, many keep a **secondary site on-prem or in a second in-country provider** rather than going cross-border.
- Peak events (visa seasons, school registration, Hajj/Umrah-related services in Saudi Arabia, month-end) drive capacity planning.

### Self-test

- Compute the composite SLA of three 99.95% services in series.
- RPO vs RTO with a Sahl example.
- Why is DR harder for a Qatar government system than for a German one?
- Name four resilience patterns and where Sahl uses each.

---

## 20. DevOps, IaC and environments

### The simple version

Instead of clicking around the Azure portal (which nobody can review or repeat), we **write the infrastructure down as code**. A robot (CI/CD) reads that code and builds the environment exactly the same way every time. To deploy to production, a human must press "approve."

### The concepts

**Infrastructure as Code (IaC)** — infrastructure defined in files, versioned in Git, reviewed in PRs, deployed by pipelines. Benefits: repeatability, review, history, drift detection, disaster recovery ("rebuild everything from code").

**Declarative vs imperative** — declarative (Bicep/Terraform): "this is the desired state"; imperative (scripts): "run these steps."

**ARM (Azure Resource Manager)** — the API every Azure deployment goes through. **ARM templates** are JSON. **Bicep** is a cleaner language that compiles to ARM.

**Bicep essentials**
- `param`, `var`, `resource`, `module`, `output`, `existing` (reference resources you didn't create).
- **Deployment scopes**: resource group, subscription, management group, tenant.
- **`what-if`** — preview changes before applying.
- **Deployment stacks** — manage a group of resources as a unit; can deny manual changes and clean up removed resources ⚠ Verify current status.
- **Azure Verified Modules (AVM)** — Microsoft-maintained modules that follow best practices.
- **`.bicepparam`** files — per-environment parameters (`dev.bicepparam`, `prod.bicepparam`).
- **Linting** — `bicep lint`, plus **PSRule for Azure** for Well-Architected checks.

**Terraform (for comparison)** — multi-cloud, huge ecosystem, needs **state** storage (often in Azure Storage with locking). Common in job postings. Bicep has no state file (ARM is the state).

**CI / CD** — **Continuous Integration**: every change builds and tests. **Continuous Delivery**: every passing change *can* be deployed; **Continuous Deployment**: it *is* deployed automatically.

**GitHub Actions** — workflows in `.github/workflows/*.yml`; **environments** (`dev`, `prod`) with **protection rules** (required reviewers, wait timers, branch restrictions) and environment-scoped variables.

**OIDC workload identity federation (GitHub → Azure)** — GitHub issues a short-lived token saying "I am repo X, branch main, environment prod"; Entra trusts it via a **federated credential** on an app registration/user-assigned managed identity. **No client secret stored anywhere.** The federated credential can be restricted to a specific environment — so only the `prod` environment (with its approval gate) can get the prod identity.

**Environments** — isolated copies (dev, test, prod). Usually separate resource groups or **separate subscriptions** (stronger isolation, separate RBAC and policies).

**Promotion** — the *same build artifact* (container image digest) moves dev → prod; you don't rebuild for prod.

**Monorepo with path filters** — workflows trigger on `paths: ['api/**']` etc.

**Naming convention** (CAF style) — `<type>-<workload>-<env>-<region>-<instance>`: `rg-sahl-dev-gwc`, `kv-sahl-prod-qac`, `psql-sahl-dev-gwc`. Consistent names make policy, cost reports, and incident response easier. Some resources (Storage accounts) can't have hyphens and must be globally unique.

**Tagging** — `environment`, `owner`, `cost-center`, `data-classification`, `workload`. Enforced by policy.

### What Sahl does

- Monorepo: `/infra` (Bicep), `/portal` (Laravel + Inertia React, both surfaces), `/services/*` (Functions + mocks: national IdP, registries, payment gateway), `/docs` (ADRs, C4, this guide).
- **Bicep** with AVM modules where sensible; `profile` param (`lean`|`gov`); `dev.bicepparam`, `prod.bicepparam`.
- **GitHub Actions**: lint/test/scan on PRs; build container images → **ACR**; deploy dev automatically on `main`; deploy prod via **environment approval**; infra `what-if` posted to PRs.
- **OIDC federation** — no Azure secrets in GitHub.
- **Teardown workflow** for the burst gov profile (and prod) to save credits.
- **Local dev**: Docker Compose — Postgres, Redis, **Azurite** (Storage emulator), **Service Bus emulator**, Mailpit, mock national IdP, mock payment gateway, mock registries.

### What GCC governments actually do

- Microsoft-centric entities often use **Azure DevOps** (Boards + Repos + Pipelines), sometimes **self-hosted** (Azure DevOps Server) for residency; GitHub Enterprise adoption is growing.
- **Change management** is formal: change advisory boards (CAB), maintenance windows, segregation between developers and operators — your GitHub environment approvals model this.
- Vendors deliver; government teams own operations — so documentation, IaC, and runbooks are contractual deliverables.

### Self-test

- Why is clicking in the portal a compliance problem?
- How does GitHub OIDC federation avoid storing secrets? How do you restrict it to prod approvals only?
- Why promote the same image instead of rebuilding for prod?
- Bicep vs Terraform: two real differences.
- Why might a government require separate subscriptions per environment?

---

## 21. Cost management

### The simple version

The cloud is a taxi meter that never stops. Architects are responsible for cost just like security: a design that's secure but costs 10× the budget is a failed design.

### The concepts

- **Pricing models**: pay-as-you-go, reservations (1–3 years, big discounts), savings plans, spot (interruptible), free tiers.
- **Always-on vs consumption** — some services bill per hour regardless of use (Firewall, App Gateway, APIM classic tiers, VPN gateways, Premium Service Bus, dedicated plans). Others bill per use (Functions consumption, Container Apps consumption, Storage).
- **Hidden costs**: private endpoints (per hour + data), public IPs, log ingestion volume (Log Analytics is a classic budget surprise), egress data transfer, backups, Defender plans.
- **Azure Cost Management** — budgets with alerts (50/80/100%), cost analysis by tag/resource group, anomaly alerts.
- **FinOps** — the practice of cloud financial management: visibility, accountability (tags/owners), optimization.

### Sahl's cost strategy

1. **Budget + alerts** on the subscription on day one.
2. **Lean profile** always-on: Burstable Postgres, consumption/serverless compute, Developer/Consumption APIM, free/basic AI Search, minimal log retention, sampling in App Insights.
3. **Gov profile** in **bursts** (deploy → demo/record → destroy) via a pipeline — measure the cost of each burst and write it in the case study ("full government profile costs ≈ $X/hour").
4. **Teardown** of dev outside working sessions where possible (Postgres can be stopped; Container Apps scale to zero).
5. Tag everything; review costs weekly.

**⚠ Verify** all prices with the Azure Pricing Calculator for Germany West Central at build time; Qatar Central prices differ.

### Self-test

- Name four always-on Azure services that drain credits.
- Why is Log Analytics ingestion a common surprise?
- How does the burst strategy itself become a portfolio talking point?

---

## 22. The decision register

Every decision so far, in one place. Each "Decided" row will become a formal ADR in `/docs/adr/`.

### Decided

| # | Area | Decision | Main alternative(s) rejected | Key trade-off accepted |
|---|---|---|---|---|
| D01 | Purpose | Fictional GCC government portal as a career portfolio PoC | Real client / real branding | No real integrations; must explain realism gaps honestly |
| D02 | Scenario | Qatar-modeled unified e-services portal | KSA, UAE, Kuwait, Bahrain, Oman, fictional state | Qatar-specific regulations to research |
| D03 | Region | PoC in Germany West Central; prod (paper) Qatar Central | Deploy PoC in Qatar Central / UAE North | Residency not actually met in PoC (ADR-001 exception) |
| D04 | Scope | 3 ministries + Digital Government Authority; certificates, permits, licensing/fees, appointments | Single ministry | More authorization and integration complexity |
| D05 | Style | Laravel modular monolith + satellite services | Plain monolith, microservices (AKS / ACA+Dapr) | Must enforce module boundaries by discipline + tests |
| D06 | Frontend | **Inertia as BFF**: one Laravel+Inertia React image, two deployments (citizen public, back-office internal) — *supersedes the original "two SPAs + API" decision* | Two SPAs + API, Next.js BFF, single deployment | No MSAL/browser-token practice; CSRF + Redis sessions to handle; mobile would need API routes later |
| D07 | UI stack | React + TS (via Inertia) + shadcn/ui + Tailwind, i18n, Arabic-first RTL | MUI, Fluent UI; TanStack Router/Query (not needed with Inertia) | Own the components; must build RTL discipline |
| D08 | Citizen identity | Entra External ID federated with mock national OIDC IdP | Laravel-native auth, External ID local accounts, direct IdP integration | Two tenants to manage; broker adds a hop |
| D09 | Employee identity | Entra workforce, app roles, entity scoping via groups | Roles only in Laravel | Depends on Entra licensing for CA/PIM features |
| D10 | Users | Individuals + businesses (delegation); no visitors | Visitors with email sign-up | — |
| D11 | Delegation | Commerce registry API decides company authorization | Claims in IdP token | Runtime dependency on registry; caching needed |
| D12 | Roles | Platform Admin, Entity Admin, Reviewer, Approver, Service Center Agent, Auditor | Simple Admin/Employee/Auditor | More policy code; enables SoD |
| D13 | Tenancy | Pool model (shared DB, `entity_id`) | DB per ministry, silo | Isolation by code (+ optional RLS) |
| D14 | Database | PostgreSQL Flexible Server, private, Entra auth | Azure SQL, MySQL, Cosmos DB | Lose Azure SQL Ledger (replace with own hash chain) |
| D15 | Citizen data | Synthetic national registry (~1,000 people), "tell us once" + consent | Minimal seed data | Must build the mock registry |
| D16 | Workflow | Realistic multi-department permit process with SLA escalation | Linear 3-step, configurable engine | Long-running orchestration complexity |
| D17 | Payments | Mock hosted page + async signed webhooks with chaos | Instant success | Must implement idempotency, outbox, reconciliation |
| D18 | Documents | Signed PDFs via Key Vault + QR verification | App-held key files | PAdES library complexity |
| D19 | Inter-ministry | APIs exposed/consumed via APIM (national integration platform model) | Direct calls | APIM cost/tier choices |
| D20 | AI | RAG over public service guides only; no PII, no tools | Status-checking agent | Less "wow"; much safer |
| D21 | Audit | Immutable (WORM) + hash-chained audit trail incl. reads | Standard app logging | Extra storage + engineering |
| D22 | Observability | App Insights + Log Analytics, dashboards, alerts | + Defender, Sentinel | No SIEM in PoC (documented) |
| D23 | Network | Designed strict (hub-spoke, Firewall, FD Premium), deployed lean; gov profile in bursts | Always strict; public-only | Two profiles to maintain in Bicep |
| D24 | IaC | Bicep (+ AVM) | Terraform, both | Azure-only skillset on this project |
| D25 | CI/CD | GitHub + Actions with OIDC federation, env approvals | Azure DevOps | — |
| D26 | Environments | dev (always) + prod (on demand) | dev/test/prod; single env | No separate test env |
| D27 | Repo | Monorepo; move Laravel skeleton into `/portal` | Multi-repo | Path-filtered pipelines |
| D28 | Local dev | Docker Compose with Azure emulators + mocks | Herd + minimal Docker | Larger compose setup |
| D29 | Notifications | Email only via Azure Communication Services (appointments/certificates) | Full notification center (SMS/in-app) | Proposed — confirm |
| D30 | Portfolio | Case-study site, demo script, interview prep doc | — | — |
| D31 | NFRs | Assumed numbers documented on paper; no load engineering | Production-scale targets | Scale claims are design-only |

### Open — you will decide these

| # | Decision | Chapter | Options |
|---|---|---|---|
| O1 | Laravel compute | 4 | Container Apps · App Service · (AKS) |
| O2 | Messaging | 11 | Service Bus · Event Grid · both |
| O3 | Permit orchestration | 13 | Durable Functions · Logic Apps · in-app state machine |
| O4 | Edge | 15 | Front Door · Application Gateway · both |
| O6 | Azure Policy guardrails | 18 | Add (recommended) · skip |
| O7 | Mock IdP implementation | 7 | Laravel Passport/OpenIddict-style app · dedicated OSS IdP (e.g., Keycloak) acting as "national IdP" |
| O8 | Cache | 10 | Azure Cache/Managed Redis · Redis container · none |

---

## 23. Glossary

| Term | Meaning |
|---|---|
| **ABAC** | Attribute-Based Access Control — rules over user/resource/environment attributes |
| **ACR** | Azure Container Registry — stores container images |
| **acr (claim)** | Authentication Context Class Reference — how strongly the user authenticated |
| **ADR** | Architecture Decision Record |
| **AKS** | Azure Kubernetes Service |
| **APIM** | Azure API Management — API gateway, policies, products, developer portal |
| **App role** | Role defined on an Entra app registration, appears in the `roles` claim |
| **ARM** | Azure Resource Manager — the deployment/control API for Azure |
| **ASG** | Application Security Group |
| **Availability Zone** | Physically separate datacenter(s) within a region |
| **AVM** | Azure Verified Modules — official reusable Bicep/Terraform modules |
| **Azurite** | Local emulator for Azure Storage |
| **BFF** | Backend-for-Frontend — server that holds tokens for a browser app |
| **Bicep** | Azure-native declarative IaC language |
| **Bounded context** | DDD boundary within which a model/terms have one meaning |
| **CAF** | Cloud Adoption Framework — Microsoft's cloud adoption guidance |
| **CIAM** | Customer Identity and Access Management |
| **CIDR** | Notation for IP ranges (`10.0.0.0/16`) |
| **Claim** | A field in a token |
| **CMK** | Customer-Managed Key |
| **Conditional Access** | Entra policies controlling sign-in based on conditions |
| **Consent record** | Evidence of a person's agreement to processing |
| **CORS** | Browser rule controlling cross-origin API calls |
| **CSP** | Content Security Policy — browser header restricting what scripts can run |
| **DDD** | Domain-Driven Design |
| **DLQ** | Dead-letter queue |
| **DR** | Disaster Recovery |
| **Egress / Ingress** | Outbound / inbound traffic |
| **Entra External ID** | Microsoft CIAM for external users (citizens/customers) |
| **Entra ID** | Microsoft workforce identity (formerly Azure AD) |
| **Federation** | Trusting identities asserted by another IdP |
| **HSM** | Hardware Security Module |
| **IaC** | Infrastructure as Code |
| **Idempotency** | Same operation repeated → same result |
| **IdP** | Identity Provider |
| **JIT provisioning** | Creating a local user record on first federated login |
| **JWKS** | JSON Web Key Set — IdP's published public signing keys |
| **JWT** | JSON Web Token |
| **KEDA** | Kubernetes Event-Driven Autoscaling |
| **KQL** | Kusto Query Language (Log Analytics) |
| **Landing zone** | Governed, pre-configured Azure foundation for workloads |
| **LoA** | Level of Assurance |
| **Managed Identity** | Azure-provided identity for a resource; no secrets |
| **Maker–checker** | One person makes, another approves |
| **MSAL** | Microsoft Authentication Library |
| **NIA Policy** | Qatar's National Information Assurance Policy |
| **NSG** | Network Security Group |
| **OBO** | On-Behalf-Of OAuth flow |
| **OIDC** | OpenID Connect |
| **Orchestration / choreography** | Central coordinator vs event-reacting participants |
| **OTel** | OpenTelemetry |
| **Outbox pattern** | Store outgoing messages in the same DB transaction as the business change |
| **PAdES** | PDF Advanced Electronic Signatures standard |
| **PCI DSS** | Payment card industry security standard |
| **PDPPL** | Qatar's Personal Data Privacy Protection Law (Law 13 of 2016) |
| **PII** | Personally Identifiable Information |
| **PIM** | Privileged Identity Management (just-in-time admin) |
| **PITR** | Point-in-time restore |
| **PKCE** | Proof Key for Code Exchange |
| **PKI** | Public Key Infrastructure |
| **Private endpoint** | Private IP for a PaaS service inside your VNet |
| **RAG** | Retrieval-Augmented Generation |
| **RBAC** | Role-Based Access Control (also Azure's own resource-access system) |
| **ReBAC** | Relationship-Based Access Control |
| **Reconciliation** | Comparing your records with an external source of truth |
| **Region pair** | Microsoft's traditional DR partner region |
| **RLS** | Row-Level Security (Postgres) |
| **RPO / RTO** | Max data loss / max downtime |
| **RTL** | Right-to-left layout |
| **Saga** | Long business transaction with compensating actions |
| **SAML** | XML-based federation standard |
| **Scope (OAuth)** | Delegated permission an app holds on behalf of a user |
| **Service principal** | Instance of an app in a tenant (enterprise application) |
| **SIEM** | Security Information and Event Management (e.g., Sentinel) |
| **SLA / SLO / SLI** | Promise / target / measurement |
| **SoD** | Separation of Duties |
| **SOC** | Security Operations Center |
| **SPA** | Single Page Application |
| **STRIDE** | Threat categories: Spoofing, Tampering, Repudiation, Info disclosure, DoS, Elevation |
| **Tenant (Entra)** | Isolated directory instance |
| **Tenant (app)** | A customer/ministry in a multi-tenant system |
| **UDR** | User-Defined Route |
| **VNet** | Azure Virtual Network |
| **WAF (security)** | Web Application Firewall |
| **WAF (framework)** | Azure Well-Architected Framework — careful, same acronym! |
| **WORM** | Write Once, Read Many (immutable storage) |
| **XSS** | Cross-Site Scripting |
| **Zero Trust** | Verify explicitly, least privilege, assume breach |

---

## 24. Learning path

Study in the same order we build. For each step: read the concept chapters here, then the Microsoft Learn material, then build.

| Step | We build | Read here | Microsoft Learn / external (search these titles) |
|---|---|---|---|
| 0 | Azure account, MFA, CLI + Bicep install, budget, first resource group | Ch. A | Microsoft Learn: **AZ-900 Azure Fundamentals** learning path; "Azure Resource Manager overview"; "What is Azure RBAC" |
| 1 | Vision, C4 Context, ADR-001 | Ch. 0, 1, 2 | Azure Architecture Center: "Architecture styles", "Design principles"; C4 model (c4model.com); "Azure geographies / Products available by region" |
| 2 | Compute decision, C4 Container diagram | Ch. 3, 4 | "Choose an Azure compute service"; Container Apps & App Service overviews |
| 3 | Azure foundation: subscription, budgets, naming, tagging, policy, GitHub OIDC | Ch. 18 (policy), 20, 21 | CAF "Ready" — naming & tagging, Azure landing zones; "Azure Policy overview"; "Configure OIDC in GitHub Actions for Azure" |
| 4 | Monorepo + Docker Compose + Laravel/Inertia skeleton (two surfaces) | Ch. 3, 20 | Azurite, Service Bus emulator docs |
| 5 | Identity: tenants, app registrations, mock national IdP, federation | Ch. 6, 7 | "Microsoft identity platform", "OAuth 2.0 auth code flow", "Entra External ID overview", "custom OIDC identity provider" |
| 6 | Authorization & multi-tenancy | Ch. 8, 9 | "App roles", "group claims"; Laravel policies; Postgres RLS |
| 7 | Data: Postgres, registry, consent | Ch. 10 | "Azure Database for PostgreSQL Flexible Server — networking, Entra auth, HA" |
| 8 | Networking (lean) + private endpoints + DNS | Ch. 15 | "What is a private endpoint", "Private endpoint DNS", "Hub-spoke network topology" |
| 9 | Integration: APIM, messaging, outbox | Ch. 11 | "API Management key concepts", "Compare Azure messaging services" |
| 10 | Certificates & signing | Ch. 14 | "Key Vault keys", "Key Vault security", "Managed HSM overview" |
| 11 | Permit workflow | Ch. 13 | "Durable Functions overview / patterns", "Logic Apps Standard" |
| 12 | Payments | Ch. 12 | Stripe's idempotency docs (excellent general reading), outbox pattern articles |
| 13 | Appointments + email | — | Azure Communication Services email |
| 14 | Audit & observability | Ch. 17 | "Immutable storage for blobs", "Application Insights OpenTelemetry" |
| 15 | AI assistant | Ch. 16 | "RAG in Azure AI Search", "Azure OpenAI data, privacy and security" |
| 16 | Gov profile burst: hub-spoke, Firewall, Front Door Premium, WAF | Ch. 15, 19 | "Azure Firewall", "Front Door Private Link origins", "Azure landing zone" |
| 17 | Security review: STRIDE, compliance mapping | Ch. 18 | Microsoft Threat Modeling Tool; official NIA Policy & PDPPL guidance (Qatar government sources) |
| 18 | Well-Architected review | All | "Azure Well-Architected Framework" + "Well-Architected Review" assessment |
| 19 | Portfolio: case study, demo, interview prep | Ch. 22 | — |

**Certification alignment**: Step 0 + Chapter A cover most of **AZ-900**. The project as a whole covers a large share of **AZ-305** (Designing Azure Infrastructure Solutions) and touches **AZ-500** (security), **SC-300** (identity), **AZ-400** (DevOps), and **AZ-204** (developer).

### The three phases (because the free credit expires after 30 days)

The free account's credit is time-boxed, so **the clock only starts when we're ready to use it**.

| Phase | Azure account | What happens |
|---|---|---|
| **1 — Design & local build** (no clock) | Not activated. CLI + Bicep installed locally | Chapter A + AZ-900 study, architecture docs (Steps 1–2), monorepo + Docker Compose, Laravel modules, mock national IdP / registry / payment gateway, both Inertia surfaces, **all Bicep written and compiled locally** (`bicep build` works without a subscription) |
| **2 — 30-day Azure sprint** | Activate free account | Foundation (budget, naming, policy, GitHub OIDC), Entra tenants + federation, deploy lean profile, private networking, integrations, signing, AI, then **gov-profile bursts in the last days**, record demos and screenshots |
| **3 — Portfolio** | Credit spent / expired; upgrade to pay-as-you-go only if needed | Case study, demo video, interview prep. The Bicep proves the environment can be rebuilt at any time |

Map to certifications: Phase 1 + Chapter A ≈ **AZ-900**; Phase 2 ≈ large parts of **AZ-104** (admin) and **AZ-305** (architecture design).

### How to study each chapter

1. Read the simple version and explain it out loud without looking.
2. Read the concepts; write your own one-line definition for each bolded term.
3. Answer the self-test questions **in writing** (these become your interview prep doc).
4. Before building, write the ADR yourself first; then compare with the guide.
5. After building, add a "What surprised me" note to the ADR. Those notes are what make your interview answers sound real.

---

*This guide is a living document. As we build, each chapter gains links to the actual ADRs, diagrams, and code.*
