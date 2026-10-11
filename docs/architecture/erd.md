# Sahl — Entity Relationship Diagrams (ERD)

> Status: **Draft v0.1** — for review. Decisions marked **❓** need your confirmation before we write migrations.
> Diagrams are [Mermaid](https://mermaid.js.org/syntax/entityRelationshipDiagram.html) — they render on GitHub and in VS Code (with a Mermaid extension).

---

## How to read an ERD

An ERD shows **tables (entities)**, their **columns (attributes)**, and how they **relate**.

**Cardinality symbols (crow's foot notation):**

| Symbol | Meaning | Laravel equivalent |
|---|---|---|
| `\|\|--o{` | one to **zero or more** | `hasMany` / `belongsTo` |
| `\|\|--\|{` | one to **one or more** | `hasMany` (at least one required by business rule) |
| `\|\|--o\|` | one to **zero or one** | `hasOne` (optional) |
| `\|\|--\|\|` | exactly one to exactly one | `hasOne` (required) |
| `}o--o{` | many to many | `belongsToMany` (pivot table) |

**Keys:** `PK` primary key · `FK` foreign key · `UK` unique.

---

## The big picture: which database owns what

The first architecture lesson in a data model is **ownership**. In a real government, Sahl does **not** own citizen identity data or company data — the national registries do. So they are **separate systems with separate databases**, and Sahl only stores **references** (QID, CR number) and **cached copies**.

```
┌─────────────────────────── Sahl platform DB (PostgreSQL) ───────────────────────────┐
│  Organization & people · Service catalog · Applications & workflow · Issued docs   │
│  Payments · Appointments · Consent · Audit · Outbox/Inbox                           │
└─────────────────────────────────────────────────────────────────────────────────────┘
        ▲ API calls via APIM (no shared DB, no cross-DB foreign keys)
        │
┌───────┴────────────┬──────────────────────┬────────────────────────┬──────────────────────┐
│ National Registry  │ Commerce Registry    │ Mock National IdP      │ Mock Payment Gateway │
│ (persons,          │ (companies,          │ (login accounts,       │ (transactions,       │
│  relations,        │  signatories)        │  OIDC clients)         │  webhooks)           │
│  addresses,        │  owned by Ministry   │  owned by national     │  owned by national   │
│  properties)       │  of Commerce         │  identity authority    │  payment platform    │
└────────────────────┴──────────────────────┴────────────────────────┴──────────────────────┘
```

**Rule:** a foreign key may **never** cross a database boundary. Across boundaries we store the **natural identifier** (`qid`, `cr_number`, `property_number`, `gateway_txn_id`) and call the owning system's API when we need fresh data.

---

## 1. Overview (relationships only)

```mermaid
erDiagram
    gov_entities ||--o{ departments : has
    gov_entities ||--o{ service_centers : operates
    gov_entities ||--o{ services : offers
    gov_entities ||--o{ employees : employs
    departments ||--o{ employees : contains

    users ||--o{ user_identities : "signs in with"
    users ||--o| citizen_profiles : "is (if citizen)"
    users ||--o| employees : "is (if employee)"
    users ||--o{ consents : gives
    users ||--o{ company_delegations : "acts for"
    companies ||--o{ company_delegations : "delegates to"

    services ||--o{ applications : "requested via"
    users ||--o{ applications : submits
    companies ||--o{ applications : "on behalf of"
    applications ||--o{ application_status_history : tracks
    applications ||--o{ documents : attaches
    applications ||--o{ review_tasks : "reviewed in"
    applications ||--o{ correction_requests : "may need"
    applications ||--o| invoices : bills
    applications ||--o{ issued_documents : produces
    applications ||--o{ appointments : "may book"

    invoices ||--o{ payments : "paid by"
    service_centers ||--o{ appointment_slots : offers
    appointment_slots ||--o{ appointments : holds

    users ||--o{ audit_events : "acts in"
```

---

## 2. Organization, people and identity

```mermaid
erDiagram
    gov_entities ||--o{ departments : has
    gov_entities ||--o{ service_centers : operates
    gov_entities ||--o{ employees : employs
    departments ||--o{ employees : contains
    users ||--o{ user_identities : "signs in with"
    users ||--o| citizen_profiles : "citizen details"
    users ||--o| employees : "employee details"
    users ||--o{ consents : gives
    consent_texts ||--o{ consents : "agreed version"
    users ||--o{ company_delegations : "acts for"
    companies ||--o{ company_delegations : "delegates to"

    gov_entities {
        uuid id PK
        string code UK "MOI, MOM, MOC, DGA"
        string name_ar
        string name_en
        string kind "ministry | authority"
        boolean is_active
    }
    departments {
        uuid id PK
        uuid gov_entity_id FK
        string code UK "MOM-PLANNING, MOI-CIVIL-DEFENSE"
        string name_ar
        string name_en
    }
    service_centers {
        uuid id PK
        uuid gov_entity_id FK
        string name_ar
        string name_en
        string address
        decimal latitude
        decimal longitude
        boolean is_active
    }
    users {
        uuid id PK
        string kind "citizen | employee"
        string display_name_ar
        string display_name_en
        string email "PII"
        string phone "PII"
        string locale "ar | en"
        timestamptz last_login_at
    }
    user_identities {
        uuid id PK
        uuid user_id FK
        string issuer "Entra tenant issuer URL"
        string subject "sub / oid claim"
        string provider "external_id | workforce"
        timestamptz last_seen_at
    }
    citizen_profiles {
        uuid user_id PK, FK
        string qid UK "PII - national ID, from registry"
        string name_ar "verified from registry"
        string name_en "verified from registry"
        date date_of_birth "PII"
        string gender
        string nationality_code
        boolean is_citizen "citizen vs resident"
        timestamptz registry_synced_at
    }
    employees {
        uuid user_id PK, FK
        uuid gov_entity_id FK "tenant scope"
        uuid department_id FK "nullable"
        string employee_number UK
        string job_title_ar
        string job_title_en
        boolean is_active
    }
    consent_texts {
        uuid id PK
        string purpose_code "e.g. REGISTRY_PROFILE_SHARING"
        int version
        text body_ar
        text body_en
        timestamptz published_at
    }
    consents {
        uuid id PK
        uuid user_id FK
        uuid consent_text_id FK
        timestamptz granted_at
        timestamptz withdrawn_at "nullable"
        string channel "web"
        string ip_address
    }
    companies {
        uuid id PK
        string cr_number UK "Commerce registry ID"
        string name_ar
        string name_en
        string status "active | suspended | struck_off"
        timestamptz registry_synced_at
    }
    company_delegations {
        uuid id PK
        uuid user_id FK
        uuid company_id FK
        string role "owner | manager | authorized_signatory"
        timestamptz verified_at "last checked with Commerce API"
        timestamptz expires_at "cache expiry"
    }
```

**Why it looks like this**

- **`users` + `user_identities` (not one table).** A person's *login* (issuer + subject from Entra) is separate from the *person*. Unique key is `(issuer, subject)` — never the email. This is how you safely support two Entra tenants, and later account linking.
- **`citizen_profiles` and `employees` as 1:1 extensions of `users`.** Shared columns live in `users`; population-specific columns live in their own table. (Pattern name: **class table inheritance**.)
- **No roles table for employees.** Roles (`Reviewer`, `Approver`…) come from **Entra app roles in the token** at login. Storing them here would create a second source of truth that drifts when someone is removed in Entra. The *roles used* are captured in each audit event instead.
- **`gov_entity_id` on employees** is the **tenant scope** for everything they can see.
- **`company_delegations` is a cache**, not the source of truth. The Commerce Registry decides; we store the last answer with an expiry and re-check (Chapter 8).
- **Bilingual columns** (`name_ar`, `name_en`) instead of a translations table — Arabic and English are fixed and both always required. ❓ (see decisions)

---

## 3. Service catalog and applications

```mermaid
erDiagram
    gov_entities ||--o{ services : offers
    services ||--o{ service_fees : charges
    services ||--o{ service_required_documents : requires
    document_types ||--o{ service_required_documents : "is required as"
    services ||--o{ applications : "requested via"
    users ||--o{ applications : submits
    companies ||--o{ applications : "on behalf of"
    applications ||--o{ application_status_history : tracks
    applications ||--o{ documents : attaches
    document_types ||--o{ documents : "typed as"
    applications ||--o| permit_details : "if permit"
    applications ||--o| license_details : "if license"
    applications ||--o| certificate_details : "if certificate"

    services {
        uuid id PK
        uuid gov_entity_id FK
        string code UK "MOM-BUILDING-PERMIT"
        string name_ar
        string name_en
        string kind "certificate | permit | license | appointment"
        string applicant_types "individual, company"
        int min_assurance_level "required LoA (acr)"
        int sla_working_days
        boolean requires_payment
        boolean is_active
    }
    service_fees {
        uuid id PK
        uuid service_id FK
        string fee_code
        string description_ar
        string description_en
        bigint amount_minor "QAR dirhams, 100 = 1 QAR"
        jsonb calculation_rule "e.g. per sqm, exemptions"
        date valid_from
        date valid_to "nullable"
    }
    document_types {
        uuid id PK
        string code UK "ARCH_DRAWING, SITE_PLAN, PASSPORT_COPY"
        string name_ar
        string name_en
        string allowed_mime_types
        int max_size_kb
    }
    service_required_documents {
        uuid service_id PK, FK
        uuid document_type_id PK, FK
        boolean is_mandatory
    }
    applications {
        uuid id PK
        string reference_number UK "MOM-2026-000123"
        uuid service_id FK
        uuid gov_entity_id FK "denormalized tenant scope"
        uuid applicant_user_id FK
        uuid on_behalf_of_company_id FK "nullable"
        string status "draft | submitted | under_review | corrections_requested | awaiting_payment | approved | rejected | issued | cancelled"
        jsonb form_data "service-specific answers"
        string workflow_instance_id "orchestrator ID (open decision O3)"
        timestamptz submitted_at
        timestamptz sla_due_at
        timestamptz decided_at
        int lock_version "optimistic locking"
    }
    application_status_history {
        bigint id PK
        uuid application_id FK
        string from_status
        string to_status
        uuid actor_user_id FK "nullable for system"
        text reason
        timestamptz created_at
    }
    documents {
        uuid id PK
        uuid application_id FK "nullable"
        uuid document_type_id FK
        uuid uploaded_by_user_id FK
        string blob_container
        string blob_path
        string sha256 "integrity"
        string mime_type
        int size_bytes
        string scan_status "pending | clean | infected"
        string classification "public | internal | restricted"
        timestamptz created_at
    }
    permit_details {
        uuid application_id PK, FK
        string property_number "ref to National Registry property"
        string building_type "villa | extension | commercial"
        int floors
        decimal built_area_sqm
        string contractor_cr_number "ref to Commerce Registry"
        string engineer_license_number
    }
    license_details {
        uuid application_id PK, FK
        uuid company_id FK
        string activity_code
        string trade_name_ar
        string trade_name_en
        date requested_start_date
    }
    certificate_details {
        uuid application_id PK, FK
        string certificate_type "good_conduct"
        string purpose "employment | visa | other"
        string destination_country_code "nullable"
    }
```

**Why it looks like this**

- **One `applications` table for all services** — the *common lifecycle* (submitted → reviewed → paid → issued) is identical across ministries. This is what makes it a **platform**: a new service is mostly configuration in `services`, not new tables.
- **Service-specific data: two layers.**
  - Things we **query, filter, report or validate strongly** (permit floors, area, property) → typed **extension tables** (`permit_details`…).
  - Long-tail form answers → **`form_data` JSONB**.
  - Trade-off: JSONB is flexible but weakly validated and hard to report on; extension tables are strict but need migrations. ❓
- **`gov_entity_id` is copied onto `applications`** even though it's reachable via `services`. That's **intentional denormalization**: every tenant-scoped query and Postgres **Row-Level Security** policy can filter on one column without a join.
- **`reference_number`** — citizens and employees never see UUIDs; they see `MOM-2026-000123`. Separate from the PK.
- **`application_status_history`** — an append-only record of every transition (the state machine's memory). The `status` column is just the latest value.
- **`lock_version`** — **optimistic locking**: two reviewers editing the same application at once → the second save fails instead of silently overwriting.
- **Money as integers (`amount_minor`)** — never floats. 150.50 QAR = `15050`.
- **Documents: metadata in DB, bytes in Blob Storage**, with a SHA-256 hash so we can prove a file wasn't changed. `scan_status` because uploaded files must be malware-scanned before employees open them (Defender for Storage malware scanning, or a scanning function).

---

## 4. Reviews, workflow and issued documents

```mermaid
erDiagram
    applications ||--o{ review_tasks : "reviewed in"
    departments ||--o{ review_tasks : "assigned to"
    employees ||--o{ review_tasks : "claimed by"
    applications ||--o{ correction_requests : "may need"
    applications ||--o{ issued_documents : produces
    documents ||--o| issued_documents : "PDF of"
    issued_documents ||--o{ issued_documents : "superseded by"

    review_tasks {
        uuid id PK
        uuid application_id FK
        uuid gov_entity_id FK "the reviewing entity (may differ from owner)"
        uuid department_id FK
        uuid assigned_employee_id FK "nullable until claimed"
        string kind "review | approval"
        string status "pending | in_progress | passed | failed | corrections_requested"
        timestamptz due_at "SLA"
        timestamptz escalated_at "nullable"
        uuid completed_by_employee_id FK "nullable"
        timestamptz completed_at
        text decision_notes
    }
    correction_requests {
        uuid id PK
        uuid application_id FK
        uuid review_task_id FK
        text message_ar
        text message_en
        timestamptz requested_at
        timestamptz resolved_at "nullable"
    }
    issued_documents {
        uuid id PK
        uuid application_id FK
        uuid document_id FK "the signed PDF"
        string kind "certificate | permit | license"
        string serial_number UK "MOI-GC-2026-004512"
        string verification_code UK "in the QR URL"
        uuid holder_user_id FK "nullable"
        uuid holder_company_id FK "nullable"
        timestamptz issued_at
        timestamptz valid_until "nullable"
        string status "valid | revoked | superseded"
        timestamptz revoked_at
        string revocation_reason
        uuid superseded_by_id FK "nullable"
        string signing_key_id "Key Vault key + version"
        string signature_algorithm "e.g. RS256 / PS256"
        string sha256
    }
    holidays {
        uuid id PK
        date holiday_date UK
        string name_ar
        string name_en
    }
```

**Why it looks like this**

- **`review_tasks` carries its own `gov_entity_id`** — Civil Defense (Interior) reviews a Municipality permit. The task belongs to the **reviewing** entity; the application belongs to the **owning** entity. That's how cross-entity collaboration works **without** giving Interior access to all Municipality data (Chapter 9).
- **Separation of duties** is enforced in Laravel policies using this table: an employee who completed a `review` task on an application can't complete its `approval` task.
- **Orchestration state is *not* here.** Timers, fan-out/fan-in and waiting live in the workflow engine (open decision O3); this table is the **business-visible** state that employees and auditors see. If we choose an in-app state machine instead, this table becomes the engine's state too.
- **`holidays`** + a configured weekend → the **working-day calendar** for SLA due dates.
- **`issued_documents` is separate from `documents`** — an uploaded drawing and an issued, signed, revocable government certificate are very different things legally. Verification (QR page) reads only this table and shows minimal data.
- **Revocation & superseding** — a corrected certificate *supersedes* the old one; the old one remains (history is never deleted) but shows "superseded" when scanned.

---

## 5. Payments

```mermaid
erDiagram
    applications ||--o| invoices : bills
    invoices ||--|{ invoice_lines : contains
    service_fees ||--o{ invoice_lines : "priced by"
    invoices ||--o{ payments : "paid by"
    payments ||--o{ payment_events : "notified by"
    reconciliation_runs ||--o{ reconciliation_items : finds

    invoices {
        uuid id PK
        uuid application_id FK
        string bill_number UK "reference the citizen pays against"
        bigint total_minor
        string currency "QAR"
        string status "unpaid | paid | cancelled | refunded"
        timestamptz due_at
        timestamptz created_at
    }
    invoice_lines {
        uuid id PK
        uuid invoice_id FK
        uuid service_fee_id FK
        string description_ar
        string description_en
        int quantity
        bigint unit_amount_minor
        bigint line_total_minor
    }
    payments {
        uuid id PK
        uuid invoice_id FK
        string gateway_txn_id UK "idempotency anchor"
        string idempotency_key UK "sent when creating the payment"
        bigint amount_minor
        string status "initiated | authorized | paid | failed | refunded"
        string method "card | bank"
        timestamptz paid_at
    }
    payment_events {
        uuid id PK
        uuid payment_id FK "nullable until matched"
        string gateway_event_id UK "dedupe duplicate webhooks"
        string event_type "payment.succeeded, payment.refunded"
        boolean signature_valid
        jsonb payload
        timestamptz gateway_created_at "for ordering"
        timestamptz received_at
        timestamptz processed_at "nullable"
    }
    reconciliation_runs {
        uuid id PK
        date settlement_date UK
        string status "running | ok | mismatches"
        int mismatches_count
        timestamptz started_at
        timestamptz finished_at
    }
    reconciliation_items {
        uuid id PK
        uuid reconciliation_run_id FK
        string gateway_txn_id
        string issue "missing_locally | missing_at_gateway | amount_mismatch | status_mismatch"
        timestamptz resolved_at
        text resolution_note
    }
```

**Why it looks like this**

- **Invoice ≠ payment.** An invoice is *what is owed*; payments are *attempts to pay it* (a citizen may fail twice, then succeed). One invoice → many payments.
- **Two unique keys make it idempotent:** `payments.gateway_txn_id` (same transaction can't be recorded twice) and `payment_events.gateway_event_id` (same webhook processed once). The database enforces idempotency even if application code has a bug — **let the database be the last line of defense**.
- **`payment_events` stores every webhook raw**, valid or not, *before* processing — so you can replay, debug, and prove what the gateway told you.
- **`gateway_created_at`** handles out-of-order events: a "succeeded" older than the current state doesn't overwrite a later "refunded."
- **Reconciliation tables** record each nightly comparison and every mismatch until someone resolves it.

---

## 6. Appointments

```mermaid
erDiagram
    service_centers ||--o{ appointment_slots : offers
    services ||--o{ appointment_slots : "for service"
    appointment_slots ||--o{ appointments : holds
    users ||--o{ appointments : books
    applications ||--o{ appointments : "related to"

    appointment_slots {
        uuid id PK
        uuid service_center_id FK
        uuid service_id FK
        timestamptz starts_at
        timestamptz ends_at
        int capacity
        int booked_count "guarded by row lock or check constraint"
    }
    appointments {
        uuid id PK
        string booking_reference UK
        uuid appointment_slot_id FK
        uuid user_id FK
        uuid application_id FK "nullable"
        string status "booked | cancelled | checked_in | completed | no_show"
        timestamptz created_at
        timestamptz cancelled_at
    }
```

**Why it looks like this**

- **The classic concurrency problem:** two citizens book the last seat at the same millisecond. Solutions: `SELECT … FOR UPDATE` on the slot row inside a transaction, or an atomic `UPDATE … SET booked_count = booked_count + 1 WHERE booked_count < capacity` and check affected rows, plus a `CHECK (booked_count <= capacity)` constraint as the safety net.
- Unique `(appointment_slot_id, user_id)` for active bookings stops double-booking the same slot.
- Timestamps are `timestamptz` and stored in **UTC**; displayed in Qatar time (UTC+3, no daylight saving).

---

## 7. Audit, messaging and notifications

```mermaid
erDiagram
    users ||--o{ audit_events : "acts in"
    users ||--o{ email_notifications : receives

    audit_events {
        bigint id PK "monotonic, for hash chain order"
        timestamptz occurred_at
        uuid actor_user_id FK "nullable for system"
        string actor_kind "citizen | employee | system"
        string actor_roles "roles from token at the time"
        uuid acting_gov_entity_id "employee tenant at the time"
        uuid on_behalf_of_company_id "nullable"
        string action "application.viewed, review.completed"
        string resource_type
        string resource_id
        uuid application_id "nullable, for fast lookup"
        text justification "required for sensitive reads"
        string outcome "allowed | denied"
        string ip_address
        string user_agent
        string correlation_id
        string prev_hash
        string hash "sha256(prev_hash + this record)"
    }
    outbox_messages {
        uuid id PK
        string aggregate_type "application, payment"
        string aggregate_id
        string message_type "ApplicationSubmitted"
        jsonb payload
        string correlation_id
        timestamptz created_at
        timestamptz published_at "null until sent"
        int attempts
    }
    inbox_messages {
        uuid message_id PK
        string consumer PK "which handler processed it"
        timestamptz processed_at
    }
    email_notifications {
        uuid id PK
        uuid user_id FK
        string template_code "appointment_confirmed"
        string locale "ar | en"
        string status "queued | sent | failed"
        string provider_message_id "ACS ID"
        timestamptz created_at
        timestamptz sent_at
    }
```

**Why it looks like this**

- **`audit_events` is append-only**: the app's DB role gets `INSERT` and `SELECT` only — no `UPDATE`/`DELETE` (enforced with Postgres grants). Each row is also shipped to **immutable Blob Storage** (Chapter 17). The **hash chain** (`prev_hash` → `hash`) makes any tampering with the DB copy detectable.
- **Audit columns are copied values, not foreign keys** where history matters (`actor_roles`, `acting_gov_entity_id`): the audit must show the truth **at that moment**, even if the employee later moves ministry.
- **`outbox_messages`** — written in the **same transaction** as the business change; a relay publishes to Service Bus/Event Grid and sets `published_at` (Chapter 11).
- **`inbox_messages`** — consumers record what they processed; composite PK `(message_id, consumer)` makes re-delivery harmless.

---

## 8. External systems (separate databases)

These belong to **other (fictional) government organizations**. They live in separate services, with their own databases, and Sahl reaches them only through APIs via APIM.

```mermaid
erDiagram
    persons ||--o{ person_relations : "related to"
    persons ||--o{ person_addresses : "lives at"
    persons ||--o{ properties : owns

    persons {
        string qid PK "11 digits, synthetic"
        string name_ar
        string name_en
        date date_of_birth
        string gender
        string nationality_code
        boolean is_citizen
        string marital_status
        string status "alive | deceased"
    }
    person_relations {
        string qid PK, FK
        string related_qid PK, FK
        string relation PK "father | mother | spouse | child | guardian"
    }
    person_addresses {
        uuid id PK
        string qid FK
        int zone_number
        int street_number
        int building_number
        string unit
        boolean is_current
    }
    properties {
        string property_number PK
        string owner_qid FK
        int zone_number
        string plot_number
        decimal land_area_sqm
        string land_use "residential | commercial"
    }
```
*National Registry (mock) — owned by the fictional Ministry of Interior's registry.*

```mermaid
erDiagram
    registry_companies ||--o{ company_people : "has"
    registry_companies ||--o{ company_activities : "licensed for"

    registry_companies {
        string cr_number PK
        string name_ar
        string name_en
        string legal_form "LLC | sole_proprietorship | WLL"
        string status
        date established_on
    }
    company_people {
        string cr_number PK, FK
        string qid PK "ref to National Registry"
        string role PK "owner | partner | manager | authorized_signatory"
        decimal share_percent
        date valid_from
        date valid_to
    }
    company_activities {
        string cr_number PK, FK
        string activity_code PK
        boolean is_primary
    }
```
*Commerce Registry (mock) — owned by the fictional Ministry of Commerce.*

```mermaid
erDiagram
    gateway_merchants ||--o{ gateway_transactions : receives
    gateway_transactions ||--o{ gateway_webhook_deliveries : notifies

    gateway_merchants {
        uuid id PK
        string name "Sahl - Ministry of Municipality"
        string webhook_url
        string webhook_secret_ref "Key Vault reference"
    }
    gateway_transactions {
        string txn_id PK
        uuid merchant_id FK
        string bill_number
        bigint amount_minor
        string status "pending | succeeded | failed | refunded"
        timestamptz created_at
        timestamptz settled_at
    }
    gateway_webhook_deliveries {
        string event_id PK
        string txn_id FK
        string event_type
        int attempt
        string chaos_mode "none | duplicate | delayed | out_of_order | bad_signature"
        int response_status
        timestamptz sent_at
    }
```
*Mock Payment Gateway — owned by the fictional national payment platform.*

The **Mock National IdP** mostly holds login credentials keyed by `qid` plus OIDC client configuration; its schema is defined by whichever IdP implementation we pick (open decision O7), so it's not modeled here.

---

## Cross-cutting conventions

| Convention | Choice | Why |
|---|---|---|
| Primary keys | **UUIDv7** (Laravel `HasUuids` default in recent versions ⚠ Verify) | Not guessable in URLs (no `/applications/1, /2, /3` enumeration of citizens' records), safe to generate in any service, and v7 is time-ordered so indexes stay efficient. `bigint` only for append-heavy internal logs |
| Timestamps | `timestamptz`, stored UTC | One truth; display in Asia/Qatar |
| Money | `bigint` minor units + currency | No floating-point rounding |
| Bilingual text | `_ar` / `_en` columns | Both languages mandatory and fixed |
| Soft deletes | **Not used** for business records; use explicit statuses (`cancelled`, `revoked`) | Government records are rarely "deleted"; statuses + history are clearer and auditable |
| Tenant scope | `gov_entity_id` on every entity-owned table | Global scopes + optional Postgres RLS |
| PII marking | Column comments / a data dictionary with classification | Drives masking in logs, export rules, encryption choices |
| External references | Natural IDs (`qid`, `cr_number`, `property_number`), no cross-DB FKs | Respects data ownership |
| Status columns | Strings validated by PHP enums (+ `CHECK` constraints) | Readable in SQL and audit; enum class is the state machine's vocabulary |

---

## ❓ Decisions to confirm before migrations

| # | Question | My recommendation | Alternative |
|---|---|---|---|
| E1 | Where does **Civil Defense** sit? | **Department of Ministry of Interior** — makes permit reviews genuinely **cross-entity** (realistic, and the best authorization lesson) | Department of Municipality (simpler, no cross-entity) |
| E2 | Where does **Electricity** review sit? | Department of Municipality for v1 (we capped at 3 ministries); note that in reality it's a utility authority | Add a 4th entity (utility authority) |
| E3 | Service-specific data | **Extension tables for core fields + JSONB for the long tail** | All JSONB (fastest to build) · all typed tables (strictest) |
| E4 | Primary keys | **UUIDv7** everywhere except append-only logs | bigint auto-increment |
| E5 | Bilingual storage | **`_ar` / `_en` columns** | Translations table / JSON column per field |
| E6 | Postgres Row-Level Security | **Add in a later step** as a second wall behind Laravel scopes | Laravel scopes only |
| E7 | Audit storage | **DB (searchable) + immutable Blob (legal copy)** | Blob only |
| E8 | Workflow state | Depends on **O3** (Durable Functions / Logic Apps / in-app). This ERD works for all three | — |

---

## Self-test

1. Why is there no foreign key from `citizen_profiles.qid` to the registry's `persons.qid`?
2. Why does `review_tasks` have its own `gov_entity_id` when `applications` already has one?
3. Which two unique constraints make payment processing idempotent?
4. Why are employee roles **not** stored in a table?
5. What's the risk if `audit_events` stored `actor_user_id` but not `actor_roles`?
6. Why are UUIDs safer than auto-increment IDs in citizen-facing URLs?
7. How does the appointment schema prevent overbooking the last seat?
