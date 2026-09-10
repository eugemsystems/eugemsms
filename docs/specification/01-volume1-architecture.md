# sERP — Enterprise School Management Platform
## Volume 1 · Master Architecture & Module Overview Blueprint
### Zimbabwe Market Edition · ECD A → Upper Six · Multi-School · Commercial SaaS

| Field | Value |
|---|---|
| Document | Volume 1 of 2 — Architecture & Module Overview |
| Status | Planning baseline (pre-development) |
| Version | 1.0 |
| Date | September 2026 |
| Companion | **Volume 2 — Detailed Functional & Technical Specification** (per-module screens, schemas, endpoints, acceptance criteria) |
| Audience | Product owner, lead architect, backend team, frontend team, mobile team, QA |

---

## Part 0 — How To Use This Document

### 0.1 The two-volume system

This blueprint is deliberately split so that the team never argues about the same thing twice.

**Volume 1 (this document)** answers *what exists and why*. It fixes the architecture, the module boundaries, the tenancy contract, the session model, and the financial doctrine. Once approved, **Volume 1 does not change during a build sprint.** If a decision here turns out to be wrong, it is amended formally with a version bump, because changing it mid-build invalidates work already shipped.

**Volume 2** answers *exactly how each module behaves*. Per module it will carry: entity-relationship diagrams, full column-level table definitions, every Livewire screen, every API endpoint with request/response payloads, validation rules, permission strings, event/listener maps, background jobs, edge cases, and Gherkin-style acceptance criteria.

**The build rule:** you never write code from Volume 1. You write code from Volume 2, and Volume 2 is written one domain at a time, immediately before that domain is built. Volume 1 is the map; Volume 2 is the turn-by-turn.

### 0.2 Reading conventions

| Convention | Meaning |
|---|---|
| `CORE-01` | Module identifier. Stable forever. Used in code namespaces, permission strings, git branches, and Volume 2 section numbers. |
| **Surface** | Where a module is exposed: `Livewire` (internal admin panel), `API` (Next.js + Flutter clients), or `Both`. |
| **Depends on** | Modules that must be installed and migrated before this one can boot. Enforced by the module manifest. |
| ⭐ | Marks a section that is architecturally load-bearing. Read these twice. |
| 🇿🇼 | Marks Zimbabwe-specific regulatory or market logic that has no generic equivalent. |

### 0.3 Glossary

| Term | Meaning in this system |
|---|---|
| **Tenant** | A paying customer of the software. A trust, a group of schools, or a single independent school. |
| **School** | A registered institution with its own centre number, head, and books. A tenant may own many. |
| **Section** | A division within a school: Infant, Junior, Lower Secondary, Upper Secondary, Sixth Form. |
| **Session** | The active `(academic_year, term)` context a user is operating in. Switchable. |
| **Term** | Zimbabwe runs three terms per calendar year. There are no semesters. The word "semester" is not used anywhere in this system. |
| **Full-time learner** | Charged a single composite fee per term regardless of subject count. |
| **Part-time learner** | Charged per subject enrolled, per term. |
| **Day scholar / Boarder** | Residency status. Drives fee structure, attendance mode, and welfare module applicability. |
| **SBP** | School-Based Project. The continuous-assessment instrument that replaced CALA in 2024. |
| **FDMS** | ZIMRA's Fiscalisation Data Management System. |
| **GL** | General Ledger. The double-entry book of record. The single source of financial truth. |

---

## Part 1 — Product Definition

### 1.1 What this product is

sERP is a **multi-tenant, modular school management platform** sold as a commercial subscription to large Zimbabwean schools, school groups, and church/trust education bodies. It runs the entire institution — not just marks and fees, but boarding, catering, farm, clinic, payroll, procurement, transport, statutory returns, and the books.

The design target is the hardest customer in the market: **a 1,400-learner boarding school with a primary and secondary section on one campus, a farm, a fleet of buses, 180 staff, a mixed USD/ZiG fee book, full-time and part-time enrolment, and a bursar who has been burned by a previous system losing prior-term balances.**

If the system satisfies that school, every smaller school is trivial.

### 1.2 Commercial positioning

The system is sold in **tiers built from module bundles**, not as one monolithic price. A rural day secondary school buys Core + Academic + Finance. Peterhouse-class boarding schools buy everything. This is why per-school module entitlement is an architectural requirement from day one, not a later add-on.

| Tier | Bundle | Typical customer |
|---|---|---|
| **Foundation** | Platform, People, Academic, basic Finance | Day schools, primary schools |
| **Professional** | + Full Finance, Fiscalisation, Communication, Transport, Library | Established urban schools |
| **Boarding** | + Boarding, Catering, Health, Discipline, Laundry, Estates | Boarding schools |
| **Enterprise Group** | + Payroll, Procurement, Farm, BI, Group consolidation, Multi-school reporting | Trusts, mission groups, school chains |

### 1.3 🇿🇼 Zimbabwean operating constraints and their design consequences

These are not caveats. Each one forces a specific architectural decision, and ignoring any of them produces a system that demos well and fails in production.

| Constraint | Design consequence (non-negotiable) |
|---|---|
| **Load shedding.** Power is intermittent at school sites. | No operation may assume an uninterrupted session. Cashiering, roll call, and attendance must survive mid-transaction power loss without orphaned records. All money-moving operations are transactional and idempotent. |
| **Intermittent connectivity.** Rural and peri-urban schools drop off the network for hours. | The mobile app and the fiscalisation client must queue locally and sync on reconnect. Nothing may be lost because the link was down. Offline queue depth is a monitored metric. |
| **Expensive mobile data.** Parents ration bundles. | API payloads are lean and paginated. The parent app must be usable on 2G. Images are aggressively compressed and served at multiple resolutions. Push and WhatsApp are preferred over data-heavy in-app polling. |
| **Dual currency (USD + ZiG) with a moving rate.** | Money is never a float. Every amount carries a currency. Every conversion carries a rate, a rate source, and a rate date. FX differences are posted to dedicated accounts, never absorbed silently. This is the single largest cause of "missing money" in Zimbabwean school books. |
| **Cash is still king.** Large volumes of physical cash cross the bursary counter. | Mandatory till sessions with opening float, blind cash-up, and variance reporting. No cashier may close a till without declaring a count. |
| **Deposits arrive with no reference.** Parents deposit into the school bank account with no student identifier. | A first-class **Unallocated Receipts / Suspense** workflow. Money is recognised on arrival and allocated later, with a full audit trail. It is never left off the books until identified. |
| **Staff turnover in the bursary.** | Every financial action is attributable to a named user, permanently. No shared logins. No deletions. |
| **Regulatory flux.** Curriculum, tax tables, and fiscalisation rules change with short notice. | Grading scales, tax bands, fee rules, and assessment models are *configuration*, not code. A curriculum change must be a settings update, not a deployment. |
| **Cyber and Data Protection Act [Chapter 12:07].** The system holds sensitive data on minors. | Consent tracking, data subject access, retention schedules, encryption at rest for sensitive fields, and breach logging are built in, not bolted on. |

### 1.4 ⭐ The seven product principles

Every design argument during the build is settled by these, in order.

1. **The ledger is the truth.** No screen, report, or cached column may contradict the General Ledger. If they disagree, the GL is right and the other thing is a bug.
2. **Nothing financial is ever deleted or overwritten.** Corrections are made by posting a reversal. History is immutable.
3. **One brain, two mouths.** Business logic lives in a single Action class per operation. Livewire and the API are both thin callers. Logic is never duplicated between them.
4. **Everything is scoped to a school.** A query without a school context is a security defect, not an oversight.
5. **Everything is scoped to a session.** A financial or academic record without an `academic_year_id` and `term_id` is a data defect.
6. **Configuration over code.** If a school might reasonably want it different, it is a setting.
7. **The past is read-only by default.** Writing into a closed period requires deliberate, approved, audited action.

---

## Part 2 — Technology Stack & Architecture Decisions

### 2.1 Stack

| Layer | Choice | Notes |
|---|---|---|
| Runtime | PHP 8.4+ | 8.5 where the host supports it |
| Framework | **Laravel 13.x** | Current LTS-line release |
| Modularity | **`nwidart/laravel-modules`** | Each domain module is a self-contained package with its own migrations, routes, providers, and tests |
| Admin UI | **Livewire 4** + Alpine.js + Tailwind CSS 4 | Server-rendered reactive admin. No SPA build step for internal users. |
| Admin components | Livewire Flux (or in-house component library) | Consistency across ~65 modules matters more than novelty |
| API auth | **Laravel Sanctum** — personal access tokens | Device-named tokens for Flutter, rotating tokens for Next.js. Passport only if a third party ever needs full OAuth2 |
| API style | REST, versioned at `/api/v1/`, JSON:API-ish resources | GraphQL rejected: too many low-bandwidth mobile clients, caching is harder |
| Web frontend | **Next.js** (staff, teacher, parent, student portals) | Consumes the API only. Zero direct DB access. |
| Mobile | **Flutter** (parent + student + staff app) | Consumes the same API. Offline cache layer. |
| Queue | Redis + Laravel Horizon | Notifications, report generation, fiscalisation, reconciliation |
| Cache | Redis, namespaced per school | |
| Search | Laravel Scout + Meilisearch (self-hosted) | Learner/staff/transaction lookup at scale |
| Database | MySQL 8.4 or PostgreSQL 16 | PostgreSQL preferred for financial workloads; MySQL for cheaper local hosting |
| Storage | S3-compatible (AWS S3 / DigitalOcean Spaces / MinIO on-prem) | |
| PDF | Browsershot/Chromium headless, with DomPDF fallback | Report cards and invoices must render identically every time |
| Excel | `maatwebsite/excel` | Imports and statutory exports |
| Permissions | `spatie/laravel-permission`, extended with school + session scope | |
| Audit | `spatie/laravel-activitylog`, extended with immutable financial journal | |
| Settings | Custom hierarchical settings engine (see CORE-03) | Off-the-shelf packages do not support the required inheritance depth |
| Observability | Laravel Pulse + Telescope (non-prod) + Sentry | |
| Testing | Pest 3/4, PHPStan level 8 | Finance modules require 90%+ coverage before merge |

### 2.2 ⭐ ADR-001 — Hybrid delivery: Livewire for admin, API for everyone else

**Decision.** The internal administrative back office is built with Livewire and served from the Laravel application directly. Every external user class — teacher, parent, student, non-admin staff — is served exclusively through the versioned REST API, consumed by Next.js on the web and Flutter on mobile.

**Rationale.** Admin screens are complex, permission-dense, table-heavy, and used by a small number of trained staff on decent connections. Livewire builds them dramatically faster than an SPA. Portal screens are simple, high-volume, used by thousands of untrained people on poor connections and cheap phones, and must be identical across web and mobile. That is exactly what a shared API is for.

**Consequence.** There are two authentication realms in the application, and they must never be confused:

| Realm | Guard | Session | Users |
|---|---|---|---|
| Admin | `web` | Cookie/session, CSRF-protected | Super Admin, School Admin, Head, Deputy, Bursar, Registrar, HOD, Senior Master, Matron, Warden, Nurse, Librarian, Storekeeper |
| Portal | `sanctum` | Bearer token, stateless | Teacher, Parent/Guardian, Student, Support Staff, Driver, Alumni |

A user can legitimately hold both — a Head of Department is an admin *and* a teacher. The identity is one record; the access surfaces are two.

### 2.3 ⭐ ADR-002 — The shared-domain rule (the most important rule in this document)

**Decision.** All business logic lives in **Action classes** inside the module's `Domain` layer. Livewire components and API controllers are adapters. Neither contains business rules.

```
Modules/Finance/
├── Domain/
│   ├── Actions/
│   │   ├── GenerateTermInvoicesAction.php     ← the logic lives here, once
│   │   ├── ReceiptPaymentAction.php
│   │   ├── AllocateReceiptAction.php
│   │   └── ReverseJournalAction.php
│   ├── DataObjects/          ← typed DTOs crossing the boundary
│   ├── Events/
│   ├── Exceptions/
│   ├── Policies/
│   └── Rules/                ← reusable validation
├── Http/
│   ├── Controllers/Api/V1/   ← thin: validate → call Action → return Resource
│   ├── Requests/             ← shared by BOTH surfaces
│   └── Resources/
├── Livewire/                 ← thin: bind form → call Action → flash result
├── Models/
├── Database/{Migrations,Seeders,Factories}
├── Routes/{web.php,api.php,console.php}
├── Config/
├── Tests/{Unit,Feature}
└── module.json               ← manifest
```

**Why this is non-negotiable.** Without it, the fee calculation drifts. The Livewire screen computes a balance one way, the parent app computes it another, and within six months the two disagree by a few dollars per learner across 1,400 learners. That is a company-ending bug in a product whose entire selling proposition is financial trustworthiness. One Action, called by both, tested once.

**Enforcement.** A static analysis rule fails CI if a Livewire component or an API controller contains a database write outside an Action call.

### 2.4 ⭐ ADR-003 — Tenancy: shared database with `school_id` row scoping

**Decision.** A single database. Every tenant-owned table carries a non-nullable `school_id`. A global Eloquent scope applies the current school filter automatically. Database-per-tenant is **not** used for the standard product.

**Rationale.**
- A trust with a primary and a secondary school needs **consolidated reporting across both**. That is a `WHERE school_id IN (...)` query in a shared database, and a distributed nightmare in separate databases.
- Sixty-five modules generate hundreds of migrations. Running those across N tenant databases on every release is an operational hazard on modest Zimbabwean hosting.
- Cross-school features (a bursar covering three schools, a group-level head, shared suppliers, a shared alumni body) are natural in one database.
- Hosting cost matters in this market. One well-indexed database is far cheaper than N.

**The escape hatch.** For a customer who contractually requires physical isolation (a government or diocesan contract), the same codebase deploys as a **dedicated single-tenant instance**. Because everything is already scoped by `school_id`, this is a deployment decision, not a code fork.

**The contract every module must obey:**

1. Every tenant table has `school_id` (indexed, foreign key, `NOT NULL`).
2. Every tenant model uses the `BelongsToSchool` trait, which registers a global scope.
3. Bypassing the scope requires the explicit `withoutSchoolScope()` call, which is logged and permission-gated.
4. Every composite index leads with `school_id`.
5. Foreign keys must never cross schools. A database-level check or an application-level validator enforces this.
6. Cache keys, queue payloads, file paths, and search indexes are all school-namespaced. `school:{id}:...`
7. Uniqueness is always *per school*: an admission number is unique within a school, not globally.

### 2.5 ADR-004 — Modular monolith, not microservices

**Decision.** One deployable application composed of strongly bounded modules.

**Rationale.** Microservices would require distributed transactions across fee billing, ledger posting, and fiscalisation. Getting a fiscal receipt, a GL journal, and a learner balance to agree across three services under intermittent Zimbabwean connectivity is a research project. In one database, it is a transaction. The modular structure gives clean boundaries and per-school toggling without the distributed cost.

### 2.6 ⭐ ADR-005 — Money is an integer, always

**Decision.** All monetary values are stored as `BIGINT` **minor units** (cents), never `DECIMAL` and absolutely never `FLOAT`. Every amount column is paired with a `currency` column. A `Money` value object handles all arithmetic.

```
amount_minor    BIGINT      NOT NULL    -- 125050  =  1,250.50
currency        CHAR(3)     NOT NULL    -- 'USD' | 'ZWG'
```

**Rationale.** Floating-point arithmetic on 1,400 learners × multiple fee components × three terms × two currencies produces rounding drift that shows up as a bursar's report being seventeen cents out. Bursars do not forgive seventeen cents. Integer minor units make that class of bug impossible.

**Rounding.** Every rounding operation uses banker's rounding, is applied at a single defined point, and the residual is explicitly assigned — never dropped.

### 2.7 ⭐ ADR-006 — Double-entry general ledger as the financial core

**Decision.** Finance is not a `fees` table with a `balance` column. It is a real double-entry accounting system. Every financial event — invoice, receipt, credit note, write-off, refund, discount, expense, payroll run, depreciation, FX revaluation — posts balanced journal entries to a chart of accounts.

**Rationale.** This is what makes "no missing funds from last term" structurally guaranteed rather than a promise. A balance is never a stored number that could get stale. It is derived from immutable journal lines, filtered by date. Any historical report can be regenerated exactly, forever. A nightly job asserts that total debits equal total credits, and raises an alarm if they ever do not.

This decision is expanded in **Part 7 — Financial Integrity Charter**.

### 2.8 ADR-007 — Append-only financial records

**Decision.** Financial tables are append-only. `UPDATE` and `DELETE` are revoked on journal tables at the database level. Corrections are new, linked, reversing entries.

**Consequence.** "Edit invoice" does not exist as an operation. It is: void the original (posting a reversal), issue a replacement, link the two. The parent's statement shows both, which is exactly what an auditor wants to see.

### 2.9 ADR-008 — Authentication strategy

| Client | Mechanism | Token lifetime | Notes |
|---|---|---|---|
| Livewire admin | Session cookie | Configurable idle timeout (default 30 min) | 2FA mandatory for Bursar, Head, Super Admin |
| Next.js portals | Sanctum PAT via secure httpOnly cookie proxy, or Authorization header | Short-lived access + refresh rotation | Next.js route handlers proxy so the token never reaches browser JS |
| Flutter app | Sanctum PAT, device-bound, stored in secure enclave/keystore | Long-lived with refresh + remote revoke | Each install is a named device the user can revoke from the portal |
| Third-party integrations | Sanctum with narrow ability scopes, IP-allowlisted | Per-integration | e.g. an accounting export partner |

**Abilities.** Tokens carry granular abilities (`fees:read`, `results:read`, `exeat:request`), so a student token cannot act with a parent's rights even if the account is linked.

### 2.10 Request lifecycle

```
                    ┌──────────────────────────────────────────┐
   Admin browser ──►│ web guard → SetSchoolContext →           │
                    │ SetSessionContext → CheckModuleEnabled → │──┐
                    │ Livewire Component                       │  │
                    └──────────────────────────────────────────┘  │
                                                                  ▼
                                                        ┌──────────────────┐
                                                        │  DOMAIN ACTION   │
                                                        │  (single brain)  │
                                                        └──────────────────┘
                    ┌──────────────────────────────────────────┐  ▲
   Next.js ────────►│ sanctum → ResolveSchoolFromToken →       │  │
   Flutter ────────►│ SetSessionContext → CheckModuleEnabled → │──┘
                    │ CheckAbility → API Controller            │
                    └──────────────────────────────────────────┘
                                       │
                                       ▼
                      Model (BelongsToSchool + BelongsToSession global scopes)
                                       │
                                       ▼
                          Events → Listeners → Queued Jobs
                        (notifications, GL posting, fiscalisation,
                         audit trail, cache invalidation, webhooks)
```

**The middleware stack is the safety net.** By the time any code in a module runs, the school and session context are already resolved and the module entitlement is already verified. No module author has to remember to check.

---

## Part 3 — Tenancy & Identity Backbone

### 3.1 The hierarchy

```
Tenant  (the paying customer — a trust, group, or independent school)
  │
  ├── Subscription  (plan, entitled modules, seats, billing status)
  │
  └── School  ×N          ← everything in the system hangs off school_id
        │  centre number, head, address, logo, motto, colours,
        │  base currency, fiscal config, timezone, MoPSE district
        │
        ├── Section ×N     Infant · Junior · Lower Secondary · Upper Secondary · Sixth Form
        │     │
        │     └── Grade/Form Level ×N    ECD A, ECD B, Grade 1..7, Form 1..6
        │           │
        │           └── Class / Stream ×N    e.g. "Form 3 Blue", "Grade 5 Chitepo"
        │
        ├── House ×N        (Khumalo, Chitepo, Nehanda, Lobengula…)
        │
        ├── Academic Year ×N  →  Term ×3  ← THE SESSION AXIS
        │
        └── Hostel ×N → Wing → Room → Bed
```

**Why `Section` exists.** A large Zimbabwean school routinely runs a primary and a secondary on one campus, under one head, with one bank account but separate fee structures, separate report card formats, separate timetables, and separate ZIMSEC centre arrangements. Modelling that as two schools breaks consolidated finance. Modelling it as one flat school breaks academics. `Section` resolves it: one school, one ledger, multiple academic configurations.

### 3.2 Cross-school users

A group bursar covers three schools. A group head oversees all. A teacher may be shared between the primary and secondary sections.

```
users                       (identity — one row per human, globally)
school_user                 (pivot: user_id, school_id, is_primary, status)
model_has_roles             (roles are assigned PER school, not globally)
user_school_preferences     (last active school, last active session)
```

**Rule.** A user's permission set is resolved as `(user, active_school)`. The same person can be Bursar at School A and merely a Viewer at School B. Switching the active school re-resolves the entire permission set, flushes the permission cache, and is written to the audit log.

### 3.3 ⭐ Role architecture

Roles are **templates**; permissions are the atoms. Every school starts from a seeded set of system role templates and may clone and modify them — a school that calls its bursar the "Finance Director" and gives them procurement approval should not need a code change.

| Category | Seeded roles |
|---|---|
| Platform | Super Admin, Support Engineer *(vendor-side, cross-tenant, heavily audited)* |
| Executive | Group Director, Headmaster/Headmistress, Deputy Head, Senior Master/Mistress |
| Academic | Head of Department, Senior Teacher, Class Teacher, Subject Teacher, Exams Officer, Librarian |
| Administrative | School Administrator, Registrar, Admissions Officer, Secretary |
| Finance | Bursar, Assistant Bursar, Cashier, Accounts Clerk, Procurement Officer, Storekeeper, Auditor *(read-only, sees everything financial, changes nothing)* |
| Boarding | Boarding Master/Mistress, Housemaster, Matron, Warden, Prefect *(limited)* |
| Welfare | School Nurse, Counsellor, Chaplain, Safeguarding Lead |
| Operations | Transport Manager, Driver, Maintenance Officer, Farm Manager, Kitchen Manager, Security/Gatekeeper |
| External | Parent/Guardian, Student, Alumni, Supplier *(portal-limited)* |

**Permission string format:** `{module}.{resource}.{action}[.{scope}]`

```
finance.invoice.create
finance.invoice.void
finance.period.reopen                    ← dangerous; separately grantable
academic.result.enter.own_subjects       ← scoped
academic.result.enter.any                ← unscoped
academic.result.publish
boarding.exeat.approve.final
```

**Four permission scopes** narrow what a permission reaches:

| Scope | Meaning |
|---|---|
| `own` | Only records the user created or owns |
| `assigned` | Only classes/subjects/hostels/routes assigned to the user |
| `section` | Everything within the user's section |
| `school` | Everything within the active school |

A subject teacher gets `academic.result.enter` scoped to `assigned`. The exams officer gets it scoped to `school`. Same permission, different reach, no duplicated code.

### 3.4 Sensitive-action controls

Certain actions carry disproportionate risk and get extra gates beyond the permission check:

| Action | Additional control |
|---|---|
| Reopen a hard-closed financial period | Two-person approval + reason + immutable log entry + email to Head |
| Void a receipt | Reason mandatory, original preserved, parent notified |
| Bulk-delete learners | Blocked entirely. Only archive exists. |
| Change a published exam mark | Creates a versioned amendment record; the original mark remains visible in audit |
| Export the full learner database | Rate-limited, logged, watermarked with the exporting user's identity |
| Impersonate a user (support) | Time-boxed, banner-visible to no one but the impersonator, fully logged, disabled in production without customer consent |

---

## Part 4 — ⭐ The Academic Session & Period Engine

> This is the part of the system that your requirement *"sessions to switch to last term… reports without missing funds from last terms"* depends on entirely. Every other module reads from it. Get this wrong and nothing downstream can be trusted.

### 4.1 The Zimbabwean calendar model

```
School
 └── AcademicYear         2026
       ├── Term 1         Jan – Apr    ┐
       ├── Term 2         May – Aug    ├── each with its own open/close state
       └── Term 3         Sep – Dec    ┘
             └── Week ×N  (for timetables, attendance registers, SBP milestones)
```

Each term carries: start date, end date, half-term break, fee due date, results publication date, report card release date, and its own **financial period state**.

Terms are **not** hard-coded. A school configures its own dates each year. The system ships with the standard MoPSE calendar as a seed, and a school edits it.

### 4.2 Session context

Every authenticated request resolves to an **active session**: `(school_id, academic_year_id, term_id)`.

- Set by middleware on every request, from the user's stored preference.
- Injected into a `SessionContext` singleton available everywhere.
- Applied as a global scope on all session-bound models.
- Displayed **permanently and prominently** in the admin UI chrome.

**The visual safety rule.** When a user is viewing anything other than the current live term, the entire interface changes colour and displays a persistent banner:

> **⚠ HISTORICAL VIEW — 2025 Term 3 (CLOSED). This period is read-only.**

This is not decoration. The single most expensive user error in school ERP systems is a bursar receipting a payment into the wrong term because the context switch was invisible. The banner is a hard requirement in the acceptance criteria for every admin screen.

### 4.3 ⭐ The period state machine

```
   ┌─────────┐   activate    ┌────────┐  soft close  ┌─────────────┐  hard close  ┌────────┐
   │ PLANNED │──────────────►│  OPEN  │─────────────►│ SOFT_CLOSED │─────────────►│ LOCKED │
   └─────────┘               └────────┘              └─────────────┘              └────────┘
                                  ▲                         │                          │
                                  │      reopen (approved)  │                          │
                                  └─────────────────────────┴──────────────────────────┘
                                        [dual authorisation + full audit + reason]
                                                                                        │
                                                                              archive   ▼
                                                                                 ┌──────────┐
                                                                                 │ ARCHIVED │
                                                                                 └──────────┘
```

| State | Read | Write | Who can post | Typical use |
|---|---|---|---|---|
| `PLANNED` | ✅ | Setup only | Admin | Next year's calendar and fee structures being prepared |
| `OPEN` | ✅ | ✅ | Everyone with permission | The live term |
| `SOFT_CLOSED` | ✅ | ⚠ Approval required | Bursar + Head approval per entry | Term has ended; late receipts and adjustments still trickling in |
| `LOCKED` | ✅ | ❌ | Nobody. Reopen required. | Books signed off. Reports are final. |
| `ARCHIVED` | ✅ | ❌ | Nobody | Beyond retention interest; may be moved to cold storage |

**Academic and financial states are separate.** A term's marks can be locked while its finances are still soft-closed, because parents pay late but marks are final on results day. Two independent state machines on the same term.

### 4.4 ⭐ The roll-over engine — why funds never go missing

This is the mechanism that satisfies the core requirement. When a term ends, the system does **not** archive and forget. It executes a controlled, reversible, fully audited roll-over.

**Term roll-over sequence:**

| # | Step | What actually happens |
|---|---|---|
| 1 | **Pre-close validation** | Trial balance must balance. Unallocated receipts must be zero or explicitly acknowledged. All till sessions must be closed. Any failure blocks the roll-over and produces an exception report. |
| 2 | **Snapshot** | An immutable, hashed snapshot of every learner balance, the trial balance, and all key registers is written to `period_snapshots`. This is the forensic anchor. If anyone ever questions a historical figure, this proves it. |
| 3 | **Carry-forward posting** | For each learner with a non-zero balance, a `BALANCE_BROUGHT_FORWARD` journal is posted into the *new* term, **linked by foreign key to the source term and the source journal lines**. The debt is not copied — it is *carried*, traceably. |
| 4 | **Credit carry-forward** | Learners in credit carry that credit forward identically. Overpayments are never absorbed. |
| 5 | **FX revaluation** | Foreign-currency balances are revalued at the closing rate; the difference posts to Unrealised FX Gain/Loss. This is the step most systems skip, and it is where ZiG-denominated balances silently evaporate. |
| 6 | **Academic promotion** | Learners advance grade/form per the promotion rules; repeaters, transfers, and leavers are handled explicitly. Class allocations are drafted for review, never auto-committed. |
| 7 | **Structure clone** | Fee structures, subject offerings, timetable templates, and hostel allocations clone into the new term as editable drafts. |
| 8 | **State transition** | Old term → `SOFT_CLOSED`. New term → `OPEN`. |
| 9 | **Roll-over report** | A signed PDF is produced and stored: opening position, closing position, carried balances, exceptions, and who authorised it. |

**The reconciliation invariant, enforced by an automated test:**

```
For every school, for every term:

  Σ(closing balances of term N)  ≡  Σ(opening balances of term N+1)

  ...for every currency, with FX movement accounted separately.

  If this identity does not hold, the roll-over is rejected and rolled back.
```

**A balance is never a stored number.** It is `SUM(debits) − SUM(credits)` over immutable journal lines up to a date. This is why a 2024 Term 2 statement printed today is byte-identical to the one printed in 2024 — the underlying facts cannot have changed.

### 4.5 Historical integrity guarantees

| Guarantee | Mechanism |
|---|---|
| A past-term report can always be regenerated exactly | GL is immutable; reports are date-filtered projections, never cached totals |
| No money vanishes at term boundaries | Carry-forward journals with FK links to source, plus the reconciliation invariant |
| A backdated transaction is always visible as such | `posted_at` (when entered) is stored separately from `effective_at` (when it applies); prior-period entries flag on reports |
| A closed period cannot be silently altered | DB-level write revocation + dual-authorisation reopen + immutable audit |
| Currency movement is never absorbed | Dedicated realised and unrealised FX accounts; every conversion stores its rate and source |
| A deleted record never existed | Soft deletes everywhere; hard delete permission does not exist in production |

---

## Part 5 — ⭐ Configuration & Extensibility Engine

> Requirement: *"the system must be highly configurable."* This part defines what that means concretely. The test is simple — **onboarding a new school with different rules must require zero code and zero deployment.**

### 5.1 Hierarchical settings resolution

Settings resolve down a chain, with the most specific defined value winning:

```
System Default
   └─► Licence Tier
        └─► Tenant
             └─► School
                  └─► Section
                       └─► Academic Year
                            └─► Term
                                 └─► User Preference
```

Each setting is **typed and schema-defined** — key, data type, default, validation rules, UI control hint, category, description, and the lowest level at which it may be overridden. The admin UI is generated from the schema, so adding a setting is a one-line registration, not a new screen.

Resolution is cached per `(school, session)` and invalidated on write.

### 5.2 What is configurable (illustrative, not exhaustive)

| Area | Examples |
|---|---|
| **Identity** | Name, logo, watermark, motto, crest, colours, letterhead, signature images, stamp, centre number, district |
| **Structure** | Section names, grade level labels, class naming pattern, house names, stream logic, subject groups |
| **Calendar** | Term dates, half-terms, public holidays, week numbering, period times, cycle length (5-day / 6-day / 10-day) |
| **Academic** | Grading scales, grade boundaries, pass marks, weighting (coursework vs exam), position calculation method, tie-break rules, subject compulsory/optional flags, pathway rules |
| **Reports** | Report card template, which fields appear, remark banks, remark authors, whether positions show, whether class averages show, release gating on fee balance |
| **Finance** | Chart of accounts, fee components, billing bases, currencies, rate source, allocation priority, credit limits, penalty rules, discount rules, invoice/receipt numbering, statement layout |
| **Fiscalisation** | Which fee components are fiscalisable, device credentials, tax categories, offline queue behaviour |
| **Boarding** | Roll call times, exeat approval chain, visiting hours, room capacity rules, gender segregation policy |
| **Communication** | Which events trigger which channel, quiet hours, per-channel cost caps, message templates, sender IDs |
| **Workflow** | Approval chains per document type, thresholds, escalation timers, delegation |
| **Security** | Password policy, session timeout, 2FA requirements per role, IP allowlists, export limits |

### 5.3 Feature flags & module entitlement

```
school_modules
  school_id, module_code, is_enabled, enabled_at, enabled_by,
  expires_at, config_json
```

Middleware `EnsureModuleEnabled` guards every module route on both surfaces. A school without the Boarding module sees no boarding navigation, gets `403 MODULE_NOT_ENABLED` from boarding endpoints, and has its boarding jobs skipped. Entitlement is driven by the subscription (SAA-01) but is manually overridable for trials and pilots.

### 5.4 Custom fields

Every major entity (learner, guardian, staff, class, invoice, supplier, asset, incident) supports school-defined custom fields without a migration.

- Definition: key, label, type (text, number, date, select, multi-select, boolean, file, currency), validation, required flag, section grouping, visibility per role, whether it appears on the API, whether it appears on printed documents.
- Storage: typed JSON column with a generated column + index for any field marked searchable.
- Automatically surfaced in Livewire forms, API resources, imports, exports, and report builders.

*Real example:* a mission school needs "Parish", "Baptism Date", and "Confirmation Status" on every learner. That is three custom field definitions, entered by their own administrator, live in under a minute.

### 5.5 Numbering series

Every document class has a configurable, **gapless**, per-school, per-year series.

```
Pattern:  {SCHOOL}/{TYPE}/{YEAR}/{TERM}/{SEQ:6}
Example:  SGC/INV/2026/T1/000482
```

Sequences are allocated inside the same database transaction as the document, using row-level locking. **Gaps are not permitted** — a gap in a receipt series is an audit finding in Zimbabwe, and "the system crashed" is not an accepted explanation. If a document fails after a number is drawn, the number is recorded as void with a reason, never silently skipped.

### 5.6 Template engine

Report cards, invoices, receipts, statements, transfer letters, testimonials, leaving certificates, exeat passes, and admission letters are all **school-editable templates** with a safe variable syntax, live preview, versioning, and per-section variants.

```
{{ school.name }}   {{ learner.full_name }}   {{ term.label }}
{{ invoice.balance | money }}   {{ results.subjects | table }}
```

Templates are versioned. A report card issued in 2025 renders with the 2025 template forever, even after the school redesigns it.

### 5.7 Workflow & approval engine

A single generic engine serves exeats, purchase orders, leave requests, fee waivers, mark amendments, period reopening, and refunds.

- Chains defined per document type per school: sequential, parallel, or conditional on amount/role/threshold.
- Delegation when an approver is on leave.
- Escalation timers with automatic notification.
- Full approval history stored on the document, permanently.

*Example — Exeat:* `Parent requests → Housemaster verifies → Boarding Master approves → Deputy Head signs off (only if leaving the province)`.
*Example — Purchase Order:* `Requisitioner → HOD → Bursar (< $500) → Head (≥ $500) → Board Chair (≥ $5,000)`.

Changing that chain is a settings edit.

---

## Part 6 — The Module Catalogue

### 6.0 Overview map

| Domain | Code | Modules | Bundle tier |
|---|---|---|---|
| A · Platform & Foundation | `CORE` | 13 | All (mandatory) |
| B · People & Organisation | `PPL` | 6 | Foundation → Enterprise |
| C · Academic | `ACA` | 11 | Foundation → Professional |
| D · Finance & Accounting | `FIN` | 14 | Foundation → Enterprise |
| E · Boarding & Welfare | `BRD` | 8 | Boarding tier |
| F · Operations & Estates | `OPS` | 7 | Professional → Enterprise |
| G · Communication & Engagement | `COM` | 8 | Professional+ |
| H · Compliance & Statutory | `CMP` | 4 | Professional+ |
| I · Intelligence & Integration | `INT` | 4 | Enterprise |
| J · Commercial / SaaS Control | `SAA` | 3 | Vendor-side |
| | | **78 modules** | |

**Dependency spine.** `CORE-01 → CORE-02 → CORE-03 → CORE-04` must exist before anything else. `FIN-01` (General Ledger) must exist before any other `FIN` module. `PPL-01` (SIS) must exist before any `ACA`, `BRD`, or fee module.

---

### Domain A · Platform & Foundation (`CORE`)

#### CORE-01 · System Installer & Provisioning
**Purpose.** Get the platform from a bare server to a running, licensed, first-school-configured system without a developer.
**Capabilities.** Browser-based install wizard (requirements check → permissions check → database credentials → migration runner with live progress → licence key activation → super-admin creation → first tenant & school → seed baseline data → finalise); headless CLI equivalent for CI/on-prem; `installed.lock` to prevent re-run; upgrade/migration runner with pre-flight backup; environment health dashboard.
**Surface.** Livewire (standalone, pre-auth) + Artisan.
**Depends on.** Nothing. This is the root.
**Key config.** Deployment mode (SaaS / dedicated / on-prem), licence server URL, default seed pack (Zimbabwe).

#### CORE-02 · Tenancy & School Registry
**Purpose.** Own the `Tenant → School → Section → Level → Class` hierarchy and enforce the `school_id` contract.
**Capabilities.** Tenant CRUD; school CRUD with full profile (centre number, MoPSE district, responsible authority, category — Government/Council/Mission/Trust/Private, band, address, GPS); section and level configuration; class/stream definitions; house definitions; school switcher with permission re-resolution; `BelongsToSchool` trait + global scope; cross-school user assignment; group-level consolidation flags.
**Surface.** Livewire.
**Depends on.** CORE-01.

#### CORE-03 · Academic Session & Period Engine ⭐
**Purpose.** Own time. Everything else reads its temporal context from here.
**Capabilities.** Academic year and three-term calendar; week generation; holiday and half-term calendars; session context middleware and singleton; session switcher UI with the historical-view banner; dual state machines (academic + financial) per term; period open/soft-close/lock/reopen with dual authorisation; roll-over orchestrator; period snapshots with hashing; reconciliation invariant checks; roll-over exception reporting.
**Surface.** Livewire (management) + API (read-only session list for clients).
**Depends on.** CORE-02.
**Key config.** Term dates, terms-per-year (default 3), auto-close rules, snapshot retention.

#### CORE-04 · Settings, Feature Flags & Custom Fields
**Purpose.** Make the product configurable per school without deployment.
**Capabilities.** Hierarchical typed settings with resolution chain and caching; auto-generated settings UI from schema; setting change audit; feature flags; per-school module entitlement with route middleware; custom field definitions per entity with automatic form/API/export surfacing; import/export of a full school configuration profile (so a new school can be cloned from a template school).
**Surface.** Livewire + API (read-only public settings for clients).
**Depends on.** CORE-02.

#### CORE-05 · Identity, Authentication & RBAC
**Purpose.** One identity, many access surfaces, scoped permissions.
**Capabilities.** Unified user records; dual guards (`web` session + `sanctum` token); token issuance with abilities and device binding; refresh/rotation; remote device revocation; 2FA (TOTP + SMS fallback) with per-role enforcement; password policy engine; login throttling and lockout; role templates with per-school cloning; the four permission scopes (`own`/`assigned`/`section`/`school`); policy layer; audited support impersonation; account linking (parent ↔ learners, staff ↔ teaching identity).
**Surface.** Both.
**Depends on.** CORE-02.

#### CORE-06 · Numbering, Templates & Document Generation
**Purpose.** Produce every printed and downloadable artefact the school issues.
**Capabilities.** Gapless transactional numbering series per document type; void-with-reason handling; template editor with safe variable syntax, live preview, and versioning; per-section template variants; PDF rendering pipeline (queued, cached, watermarked); bulk generation with progress tracking; digital signature and stamp overlay; QR verification codes on issued documents so a third party can verify authenticity.
**Surface.** Livewire (authoring) + API (retrieval).
**Depends on.** CORE-04.

#### CORE-07 · Workflow & Approvals Engine
**Purpose.** One approval engine for every module.
**Capabilities.** Chain definitions (sequential / parallel / conditional-on-threshold); delegation; escalation timers; approval history embedded on documents; bulk approval queues; mobile approval via API; approval-required-notification fan-out; SLA reporting on approver responsiveness.
**Surface.** Both.
**Depends on.** CORE-05.

#### CORE-08 · Audit, Activity & Data Integrity
**Purpose.** Answer "who changed what, when, from where" — forever.
**Capabilities.** Model-level change logging (before/after diff, user, IP, device, session context); immutable financial audit stream written to append-only storage; login/logout/failed-attempt log; permission change log; export and impersonation log; tamper-evident hash chaining on the financial stream; audit search and export for auditors; retention policy per record class.
**Surface.** Livewire (Auditor role) + API (limited).
**Depends on.** CORE-05.

#### CORE-09 · Notification Orchestration Bus
**Purpose.** A single dispatch layer that every module publishes to, with channel selection, cost control, and delivery accounting.
**Capabilities.** Channel drivers (SMS, WhatsApp, Email, Push, In-App); per-event channel routing rules; recipient resolution (learner → guardians → which guardian → fallback); template binding with variable injection; quiet hours; per-school monthly cost caps with hard stop; delivery status tracking and retry; per-message cost attribution; deduplication; opt-out honouring; full send log.
**Surface.** Livewire (rules, log, cost) + API (in-app inbox).
**Depends on.** CORE-04. Feeds every domain.

#### CORE-10 · File Vault & Media Management
**Purpose.** Store, secure, and serve every document and image.
**Capabilities.** S3-compatible storage with school-namespaced paths; signed short-lived URLs; virus scanning on upload; image processing and multi-resolution variants for low-bandwidth clients; document categorisation and expiry tracking (e.g. birth certificate, national registration, medical, immunisation, transfer letter); per-document access control; storage quota per school; bulk upload; OCR-assisted filing (optional).
**Surface.** Both.
**Depends on.** CORE-05.

#### CORE-11 · Data Import & Migration Toolkit
**Purpose.** Get a school's existing mess into the system. This module determines whether onboarding takes two days or two months, and therefore determines whether the product is commercially viable.
**Capabilities.** Guided importers for learners, guardians, staff, subjects, classes, historical marks, **opening fee balances**, suppliers, assets, and inventory; downloadable templates; column mapping UI; dry-run with a full validation report before any write; duplicate detection and merge; partial import with error rows returned as a correction file; **opening-balance import that posts proper GL journals rather than writing balance columns**; rollback of an entire import batch; import audit.
**Surface.** Livewire.
**Depends on.** CORE-04, FIN-01 (for balance imports).

#### CORE-12 · Jobs, Scheduling & Observability
**Purpose.** Keep the background machinery visible and reliable.
**Capabilities.** Horizon-backed queue management with per-school queue tagging; scheduled task registry with last-run/next-run/duration; failed job inspection and safe retry; long-running job progress surfaced to users; system health dashboard (queue depth, failure rate, storage, DB, cache, gateway reachability, fiscalisation backlog); alerting thresholds; maintenance mode with a friendly per-school notice.
**Surface.** Livewire.
**Depends on.** CORE-01.

#### CORE-13 · Backup, Restore & Disaster Recovery
**Purpose.** Survive a server loss without losing a term of fees.
**Capabilities.** Scheduled encrypted database + file backups to remote storage; per-school logical export; retention and rotation policy; **automated restore verification** (a backup that has never been test-restored is not a backup); point-in-time recovery guidance; restore runbook; backup status in the health dashboard; downloadable school data export for contract exit.
**Surface.** Livewire.
**Depends on.** CORE-12.

---

### Domain B · People & Organisation (`PPL`)

#### PPL-01 · Student Information System (SIS)
**Purpose.** The learner master record. Everything academic, financial, and pastoral hangs off it.
**Capabilities.** Full learner profile (bio-data, photo, national registration number, birth certificate number, gender, DOB, nationality, home language, religion, address, GPS zone); 🇿🇼 documentation vault with expiry tracking and cross-border learner permits; **enrolment type (`FULL_TIME` / `PART_TIME`) as a first-class field driving billing**; residency status (`DAY` / `BOARDER` / `WEEKLY_BOARDER`); grade/form level, class, stream, house, pathway; sibling linkage; medical summary flags surfaced to authorised roles; special educational needs register; prior-school history and transfer-in records; status lifecycle (Applicant → Enrolled → Active → Suspended → Transferred → Graduated → Withdrawn → Archived) with reason codes; learner timeline aggregating academic, financial, disciplinary, health, and boarding events into one chronological view; ID card generation with barcode/RFID/QR; bulk operations (promote, transfer, assign house, assign class).
**Surface.** Livewire (full) + API (self and guardian views).
**Depends on.** CORE-02, CORE-03.
**Key config.** Admission number format, required documents, mandatory fields, custom fields, promotion rules.

#### PPL-02 · Admissions & Enrolment CRM
**Purpose.** Turn enquiries into enrolled, paying learners — and give the school a commercial pipeline view.
**Capabilities.** Public online application form (API-driven, embeddable, mobile-friendly); enquiry pipeline with stages and owners; application fee collection at submission; document upload by applicants; entrance examination scheduling, marking, and ranking; interview scheduling and scorecards; waiting list with automatic promotion; sibling and alumni-child priority rules; offer letter generation and acceptance tracking; **acceptance deposit with automatic conversion to a fee credit on enrolment**; one-click conversion of an accepted applicant into a full learner record with zero re-keying; intake capacity management per level; conversion funnel analytics.
**Surface.** Livewire (management) + API (public form + applicant portal).
**Depends on.** PPL-01, FIN-02.

#### PPL-03 · Guardian, Family & Relationship Management
**Purpose.** Model real Zimbabwean family structures accurately, because getting this wrong breaks both billing and communication.
**Capabilities.** Guardian records independent of learners; many-to-many learner ↔ guardian with relationship type (Father, Mother, Guardian, Grandparent, Sponsor, Employer, Church, NGO); per-relationship flags — **fee responsible**, primary contact, emergency contact, may collect the child, may receive results, custody/court restrictions; **split fee liability by percentage or by component** (father pays tuition, employer pays levy, NGO pays boarding); sponsor and corporate-payer accounts; family/household grouping for sibling discounts and combined statements; guardian portal account provisioning; communication preferences per guardian; guardian occupation and employer for bursary assessment.
**Surface.** Livewire + API (guardian portal).
**Depends on.** PPL-01.

#### PPL-04 · Staff & Human Resources
**Purpose.** The staff master record and everything employment-related short of paying them.
**Capabilities.** Staff profiles (personal, next of kin, qualifications with certificate uploads, professional registration numbers, employment history); contract management (permanent / contract / relief / attachment) with expiry alerts; establishment and post management against approved staffing levels; department and reporting lines; teacher–subject–class allocation matrix with **automatic workload counters and overload warnings**; leave management (annual, sick, maternity, paternity, compassionate, study) with balances, accruals, and approval chains; duty rosters (teacher-on-duty, weekend boarding master, exam invigilation) with fairness balancing; attendance and time tracking for support staff; performance appraisal cycles and lesson observation records; disciplinary and grievance register; training and CPD log; staff document expiry tracking (contract, medical, police clearance, teaching certificate).
**Surface.** Livewire + API (staff self-service).
**Depends on.** CORE-05, CORE-07.

#### PPL-05 · Payroll & Statutory Deductions 🇿🇼
**Purpose.** Pay staff correctly and file with ZIMRA and NSSA on time. For a 180-staff school this is a genuine ERP module, not a spreadsheet.
**Capabilities.** Pay grades and salary scales with notches; earnings and deductions catalogue (basic, housing, transport, responsibility, acting, overtime, relief periods, bonus); **multi-currency payroll — split USD/ZiG salary components**; 🇿🇼 **PAYE calculation against separate USD and ZiG progressive tables**, updated as configuration when ZIMRA revises bands; 🇿🇼 **3% AIDS Levy on PAYE due**; 🇿🇼 **NSSA pension (employer + employee) with insurable-earnings ceiling**, and NSSA APWCS workers' compensation; 🇿🇼 **ZIMDEF manpower development levy at 1% of payroll**; 🇿🇼 NEC dues per applicable Collective Bargaining Agreement; loans, advances, and third-party deductions (funeral policy, medical aid, union, staff school fees) with automatic offset against the staff member's own children's fee account; payroll run with preview, approval, and lock; payslip generation and secure distribution; bank payment file export; 🇿🇼 statutory return preparation — **monthly P2 (PAYE), NSSA, NEC, ZIMDEF and AIDS Levy all due by the 10th of the following month**, and the **annual ITF16 PAYE reconciliation due 31 January**; full GL posting of every payroll run.
**Surface.** Livewire + API (payslip access).
**Depends on.** PPL-04, FIN-01.
**Key config.** Tax tables per currency per year, NSSA ceiling, NEC rates, pay calendar, GL account mapping.

#### PPL-06 · Alumni & Institutional Development
**Purpose.** Zimbabwean schools of this class have powerful old-boy/old-girl networks that fund infrastructure. This module monetises that relationship.
**Capabilities.** Automatic alumni record creation on graduation with full academic history preserved; alumni directory with year groups and houses; career and further-education tracking; alumni portal with self-service profile update; event management (reunions, founders' day, sports galas) with ticketing and payment; **donation and pledge management with pledge schedules, reminders, receipting, and GL posting**; capital campaign tracking against targets; donor recognition tiers; bursary endowment linkage — an alumnus funds a named bursary that flows into FIN-07; alumni communication segmentation.
**Surface.** Livewire + API (alumni portal).
**Depends on.** PPL-01, FIN-01, CORE-09.

---

### Domain C · Academic (`ACA`)

> 🇿🇼 **Critical curriculum update.** The Ministry of Primary and Secondary Education introduced the **Heritage-Based Curriculum Framework 2024–2030**, which **replaced Continuous Assessment Learning Activities (CALA) with School-Based Projects (SBPs)** from May 2024, at one project per learning area per year. Primary is streamlined to six core learning areas. Secondary learners follow a **two-route pathway** — academic or vocational/skills — with O-Level built on five compulsory learning areas up to a maximum of eight, and A-Level on a minimum of three. Your original specification named CALA; the system must model **SBP as the forward standard, CALA as an archived legacy model**, and make the assessment framework configurable, because national implementation guidance is still evolving.

#### ACA-01 · Curriculum, Learning Areas & Pathways 🇿🇼
**Purpose.** Model what is taught, to whom, under which national framework.
**Capabilities.** Subject/learning-area catalogue with codes, ZIMSEC subject codes, and Cambridge equivalents; **curriculum framework versioning — Heritage-Based (2024–2030), Competence-Based (2015–2022, archived), Cambridge** — running in parallel so historical records render under the framework that applied at the time; learning area grouping (Languages, Sciences, Commercials, Humanities, Practicals, Vocational); **two-route pathway definitions (Academic / Vocational-Skills) with per-pathway subject rules**; compulsory vs elective flags per level; **subject-count constraints enforced at enrolment (O-Level five compulsory, maximum eight; A-Level minimum three)**; prerequisite chains (Form 4 result gating A-Level subject selection); syllabus document repository; scheme-of-work templates.
**Surface.** Livewire + API (subject lists for portals).
**Depends on.** CORE-03, CORE-04.

#### ACA-02 · Class, Stream & Subject Enrolment
**Purpose.** Put the right learners in the right classes taking the right subjects — and produce the subject count that drives part-time billing.
**Capabilities.** Class/stream creation with capacity, class teacher, and room; automatic and manual learner allocation with balancing rules (ability banding, gender balance, house spread); **per-learner subject enrolment with add/drop history and effective dates** — this is the authoritative source for `PER_SUBJECT` billing; option block management for A-Level combinations; validation against pathway and subject-count rules; class lists and subject registers; mid-term movement between classes with full history; bulk operations.
**Surface.** Livewire + API (learner/parent views their subjects).
**Depends on.** PPL-01, ACA-01.
**Billing link.** Every add/drop emits an event that FIN-02 consumes to raise a pro-rated charge or credit for part-time learners.

#### ACA-03 · Timetable & Scheduling Engine
**Purpose.** Generate a workable timetable and stop double-bookings.
**Capabilities.** Period structure configuration (periods per day, durations, breaks, assembly, prep, cycle length — 5-day, 6-day, or 10-day); constraint definition (teacher availability, maximum consecutive periods, subject spread across the week, laboratory and practical room requirements, double-period rules, games afternoons); **automated generation with conflict resolution**; real-time clash detection on any manual edit — teacher, room, class, or learner (critical for A-Level option blocks where individual learners have unique timetables); substitution/cover management with automatic notification of the covering teacher; room and laboratory allocation; 🇿🇼 **ZIMSEC examination timetable planner** that maps public exam sessions against normal lessons and flags disruption to non-examination classes; per-learner, per-teacher, per-room, and per-class timetable views; printable and exportable formats.
**Surface.** Livewire (build) + API (view for teachers, learners, parents).
**Depends on.** ACA-02, PPL-04.

#### ACA-04 · Attendance
**Purpose.** Know who is present, where, and tell parents fast when they are not.
**Capabilities.** Multiple modes — daily registration, per-period, per-subject, boarding roll call, activity attendance; teacher marking via API on mobile with offline queueing; biometric/RFID/QR hardware hooks; absence reason codes (sick, exeat, suspended, sports, funeral, unexplained); late arrival tracking; **automatic parent notification on unexplained absence within a configurable window** — the highest-value single feature for parent perception of the product; attendance percentage per learner per term, published on the report card; chronic absenteeism flagging into the early-warning system (INT-03); statutory attendance registers for MoPSE inspection; teacher marking compliance monitoring.
**Surface.** Livewire (registers, reports) + API (teacher marking, parent view).
**Depends on.** ACA-02, CORE-09.

#### ACA-05 · Assessment, Grading & Report Cards 🇿🇼
**Purpose.** Capture marks, compute grades under any framework, and produce a report card the head is proud to sign.
**Capabilities.** Assessment type catalogue (topic test, mid-term, end-of-term, mock, practical, oral, project) with individual weightings; **configurable grading scales — ZIMSEC O-Level (A–U), A-Level (A–E, with points), Grade 7 unit grades, primary Distinction/Merit/Credit/Pass, and Cambridge (A*–U)** — all as data, so a school can define its own; weighted aggregation of coursework and examination components; mark entry via web and mobile with validation, save-as-draft, and submission lock; **mark amendment creates a versioned record — the original is never overwritten**; class position, stream position, and overall position with configurable tie-break rules; subject and class averages; grade distribution analysis; comment banks with per-subject and per-grade suggestions plus free text; class teacher, head of department, and head remarks; **report card generation with per-section templates, school crest, signatures, attendance, conduct, SBP outcome, and position**; batch generation to PDF with progress tracking; **configurable release gating — optionally withhold a report card when the fee balance exceeds a threshold**, with per-learner override; publication to the parent portal and mobile app with a notification; historical report retrieval under the template that applied at the time; transcript and cumulative record generation; progress trend charts across terms and years.
**Surface.** Livewire (configuration, batch, oversight) + API (teacher mark entry, parent/learner viewing).
**Depends on.** ACA-02, ACA-04, CORE-06.

#### ACA-06 · School-Based Projects (SBP) & Legacy CALA 🇿🇼
**Purpose.** Manage the continuous-assessment instrument that now carries part of the national mark.
**Capabilities.** **SBP as the primary model — one project per learning area per year**, per Circular 9 of 2024; project brief creation and distribution; milestone and stage tracking with deadlines; learner evidence submission (documents, photographs, video) through portal and mobile; rubric-based marking with criteria weighting; teacher moderation and internal verification workflow; head of department sign-off; **portfolio compilation per learner per learning area**; export in the format required for national submission; **legacy CALA model retained read-only** so pre-2024 records render correctly and archived cohorts remain intact; framework switch controlled by configuration, not code, because implementation guidance continues to evolve.
**Surface.** Livewire (setup, moderation) + API (learner submission, teacher marking).
**Depends on.** ACA-01, ACA-05, CORE-10.

#### ACA-07 · Examinations Administration
**Purpose.** Run internal and public examinations as controlled processes.
**Capabilities.** Examination session setup (internal end-of-term, mock, public); candidate entry management; **seating plan and hall allocation with index number assignment**; invigilation rostering; question paper repository with encrypted storage and time-locked release; script tracking (issued → collected → marked → moderated → returned); double-marking and moderation workflow; mark capture by paper and component with aggregation rules; special arrangements register (extra time, separate room, reader, scribe); malpractice incident register; results processing, statistical analysis, and provisional/final release control; historical results archive by cohort.
**Surface.** Livewire + API (limited).
**Depends on.** ACA-05.

#### ACA-08 · Learning Management System (LMS)
**Purpose.** Digital teaching and homework, usable on a cheap phone over a weak connection.
**Capabilities.** Course space per subject per class; content repository (notes, past papers, syllabi, worksheets, audio, video) with size-aware delivery and download-for-offline in the mobile app; assignment creation with deadlines, attachments, and rubrics; **submission pipeline with deadline locks, late-submission policy, and resubmission control**; teacher marking with inline annotation and feedback; similarity/plagiarism logging across submissions in the same class; discussion threads with moderation; announcement posting per class; progress tracking and non-submission chasing; gradebook sync into ACA-05 so LMS marks flow into the term aggregate without re-entry.
**Surface.** Livewire (oversight) + API (primary surface — teacher and learner apps).
**Depends on.** ACA-02, CORE-10.

#### ACA-09 · Computer-Based Testing (CBT)
**Purpose.** Deliver secure online assessment.
**Capabilities.** Question bank with tagging by topic, difficulty, and syllabus objective; item types (MCQ, true/false, matching, fill-in, short answer, essay, file upload); paper assembly manually or by rule (e.g. 20 questions, mixed difficulty, from Topic 3); randomised question and option ordering per candidate; scheduling with access windows, duration, and per-learner extensions; browser focus monitoring and tab-switch logging; **auto-save with resumption after disconnection or power loss** — mandatory given load shedding; automatic marking for objective items with instant scoring; manual marking queue for written items; result release control; item analysis (difficulty index, discrimination) to improve the bank; direct transfer of final scores to the gradebook.
**Surface.** Livewire (authoring, monitoring) + API (candidate delivery).
**Depends on.** ACA-08.

#### ACA-10 · Library & Textbook Management
**Purpose.** Control the book stock, which in a Zimbabwean school is a significant asset and a significant loss centre.
**Capabilities.** Catalogue with accession numbers, ISBN lookup, classification, copies, and condition grading; barcode/QR labelling; circulation (issue, return, renew, reserve) via scanner; **bulk textbook issue and return by class at term start and term end** — the highest-volume library operation in this market; loan periods and limits by borrower type; overdue tracking with automated reminders; **fine and lost-book charging that posts directly to the learner's fee account through FIN-02**; stock-take with variance reporting; acquisition requests and supplier ordering; reading history and popular-title reporting; digital resource links into the LMS.
**Surface.** Livewire + API (catalogue search, my-loans).
**Depends on.** PPL-01, FIN-02.

#### ACA-11 · Teaching Quality, Lesson Planning & Supervision
**Purpose.** Give the head and HODs evidence of what is actually being taught.
**Capabilities.** Scheme of work submission and approval per subject per term; lesson plan submission with HOD review and comments; syllabus coverage tracking against planned versus delivered; exercise book / marking monitoring records; **lesson observation with configurable rubrics** and post-observation feedback; departmental meeting minutes; teacher performance dashboard combining coverage, marking timeliness, attendance marking compliance, and results outcomes; professional development planning linked to PPL-04.
**Surface.** Livewire + API (teacher submission).
**Depends on.** ACA-02, PPL-04.

---

### Domain D · Finance & Accounting (`FIN`)

> This domain is the commercial heart of the product and the reason a school switches systems. It is specified in more depth than the others, and **FIN-01 must be built first** — every other finance module posts to it.

#### FIN-01 · Chart of Accounts & General Ledger ⭐
**Purpose.** The double-entry book of record. The single source of financial truth for every other module.
**Capabilities.** Configurable chart of accounts with a Zimbabwean school template seeded (assets, liabilities, equity, income, expenses, with fee income segmented by component); account hierarchy and rollups; cost centres and departments (Primary, Secondary, Boarding, Farm, Transport, Tuckshop) for departmental P&L; **journal entry engine — every financial event in every module posts balanced double-entry journals**; manual journals with approval; **append-only enforcement at the database level, corrections only by reversal**; `posted_at` versus `effective_at` separation so backdating is always visible; multi-currency journals with per-currency and consolidated balances; **nightly trial balance assertion job that alarms on any imbalance**; period-scoped queries; opening balance import that posts proper journals; drill-down from any report figure to the originating transaction and document.
**Surface.** Livewire.
**Depends on.** CORE-03, CORE-05.

#### FIN-02 · Fee Structure & Billing Engine ⭐
**Purpose.** Decide what every learner owes, this term, in which currency — including your full-time versus part-time distinction. This is the most configuration-dense module in the system.
**Capabilities.**
- **Fee component catalogue** — tuition, development levy, boarding, examination, sports, ICT, library, medical, transport, uniform, textbook, activity, insurance, building fund, each with its own currency, GL account, tax treatment, and refundability rule.
- **Billing bases per component:** `FLAT_PER_TERM` · `PER_SUBJECT` · `PER_MONTH` · `PER_DAY` · `PER_UNIT` · `ONE_OFF` · `USAGE_BASED` · `TIERED`.
- **Full-time billing:** a single composite structure applied per level/section/residency, independent of subject count.
- **Part-time billing:** `charge = Σ(subject_rate × enrolled_subjects)`, with per-subject rates, per-subject-group rates, or a uniform rate; automatic recalculation on subject add/drop with pro-rating from the effective date; optional minimum charge, maximum cap, and volume discount bands (e.g. a lower per-subject rate from the fifth subject).
- **Applicability rule engine** — a structure targets any combination of section, level, class, enrolment type, residency, pathway, nationality, house, gender, entry cohort, or custom field value. Rules are evaluated in priority order with an explicit, inspectable resolution trace: *"this learner was billed $X because rules 3 and 7 matched."*
- **Mixed-currency invoicing** — tuition in USD, levy in ZiG, on one invoice, with a stated conversion rate.
- **Proration** for mid-term joiners, leavers, and residency changes (a boarder becoming a day scholar mid-term), by day or by week.
- **Recurring versus one-off** charge scheduling.
- **Ad hoc charging** — a broken window, a lost textbook, a replacement ID card, a trip — posted to an individual learner or bulk-applied to a class.
- **Structure versioning and cloning** — a new term clones last term's structure as an editable draft; the version that produced a historical invoice is preserved forever.
- **Preview and simulation** — run the billing engine against a term and see every learner's computed charge, with variance against last term, *before* committing. Nothing is invoiced until a human approves the preview.
**Surface.** Livewire (all configuration and runs) + API (read-only fee schedule for the parent portal).
**Depends on.** FIN-01, PPL-01, ACA-02.

#### FIN-03 · Invoicing, Statements & Debtor Management
**Purpose.** Issue the bill, chase the money, and always be able to prove the position.
**Capabilities.** Batch term invoicing driven by FIN-02, with preview → approve → commit; individual and supplementary invoicing; **invoice versioning — void and reissue with linkage, never silent edit**; credit notes with reason codes and approval; **split invoicing across multiple responsible guardians by percentage or component** per PPL-03; consolidated family statements across siblings; running statement of account per learner and per guardian, generated from GL journals for any date range including closed periods; **aging analysis (current, 30, 60, 90, 120+ days) per learner, class, section, and school**, in each currency; automated reminder ladder with configurable escalation across SMS, WhatsApp, and email; payment plan and instalment agreements with schedule tracking and breach flagging; **fee waiver and write-off workflow with approval chain and mandatory reason** — write-offs post to a dedicated expense account and are never disguised as payments; debtor risk scoring; handover-to-collections marking; downloadable statements and invoices from the parent portal.
**Surface.** Livewire + API (parent statement and invoice retrieval).
**Depends on.** FIN-02, CORE-06, CORE-09.

#### FIN-04 · Receipting, Cashiering & Till Control ⭐
**Purpose.** Take money safely. This is where cash actually goes missing, so the controls are deliberately strict.
**Capabilities.** Multi-tender receipting (cash, bank transfer, mobile money, card, cheque, in-kind, journal transfer); **mandatory till sessions** — a cashier opens with a declared float, transacts, and closes with a **blind count** before the system reveals the expected figure, producing a variance record that requires explanation above a threshold; per-cashier and per-till reporting; **payment allocation engine** with configurable priority (oldest-first, component priority such as tuition before levy, or manual allocation) and full visibility of which invoice lines a receipt settled; part-payment and over-payment handling with credit creation; **Unallocated Receipts / Suspense workflow** — a bank deposit with no learner reference is receipted into suspense immediately so the money is *on the books from the moment it arrives*, then matched later with an audit trail; receipt printing and instant SMS/WhatsApp confirmation to the payer; receipt void with reason, preserving the original; 🇿🇼 automatic routing of commercial receipts to fiscalisation (FIN-13); daily banking sheet and cash-up pack; end-of-day summary by tender type and currency.
**Surface.** Livewire (primary — this is a bursary counter tool) + API (payment confirmation lookup).
**Depends on.** FIN-01, FIN-03.

#### FIN-05 · Payment Gateways & Reconciliation 🇿🇼
**Purpose.** Let parents pay from their phones, and prove every cent that arrives matches a receipt.
**Capabilities.** **Driver-based gateway abstraction** so a school can enable, disable, or swap providers by configuration. Zimbabwean provider landscape as of 2026:

| Provider | Coverage | Notes |
|---|---|---|
| **ContiPay** | Visa/Mastercard with 3DS, EcoCash USD & ZWL, InnBucks, OneMoney, TeleCash, ZIPIT, ZIMSWITCH, O'Mari, Mukuru, direct deposit | Widest single-integration coverage; also supports disbursements (useful for refunds) |
| **Pesepay** | EcoCash (best push-prompt UX in market), cards, wallets | Has a **Dart SDK — directly relevant to the Flutter app** |
| **Paynow** | EcoCash, OneMoney, TeleCash, ZIMSWITCH, Visa/Mastercard via Express | Ubiquitous, well-known to parents, mature PHP SDK |
| **ZB Smile&Pay** | EcoCash, OneMoney, O'Mari, InnBucks, SmileCash, cards | Bank-backed alternative |

Additional capabilities: hosted checkout and express/push-to-phone flows; **webhook receiver with signature verification, replay protection, and idempotency keys**; status polling as a fallback for missed webhooks; automatic receipting and GL posting on confirmed settlement; **daily automated reconciliation — gateway settlement report versus system receipts versus bank statement versus GL**, producing an exception list rather than a silent pass; bank statement import (CSV/OFX) with rule-based auto-matching and a manual matching workbench; unmatched item aging; gateway fee accounting posted to a dedicated expense account; refund and disbursement handling.
**Surface.** Livewire (config, reconciliation workbench) + API (payment initiation from portal and app).
**Depends on.** FIN-04.

#### FIN-06 · Multi-Currency & FX Engine ⭐ 🇿🇼
**Purpose.** Handle USD and ZiG without losing money in the gaps. This module exists because currency mishandling is the leading cause of unexplained variance in Zimbabwean school books.
**Capabilities.** Currency registry with decimal precision and display formats; **base/functional currency per school** (typically USD) plus transaction currency and reporting currency; exchange rate tables with effective dating and source attribution (RBZ interbank, auction, or school-set rate) — every rate used is stored with the transaction, never re-derived later; scheduled rate import with manual override and approval; **realised FX gain/loss** posted when a ZiG payment settles a USD invoice at a different rate than the invoice carried; **unrealised FX gain/loss** on revaluation of open balances at period close; rate-change impact simulation before applying a new rate; currency conversion audit trail on every converted figure; reporting in transaction currency, base currency, or both side by side; per-currency trial balance.
**Surface.** Livewire.
**Depends on.** FIN-01.

#### FIN-07 · Scholarships, Bursaries & Discounts
**Purpose.** Reduce fees in a controlled, auditable, budgeted way.
**Capabilities.** Discount catalogue (staff child, sibling, bursary, orphan/vulnerable, sports scholarship, academic scholarship, early-settlement, church/mission, corporate) as percentage or fixed amount, applicable to all or specific components; automatic rules (nth sibling gets X%, staff children get Y%) alongside individually granted awards; award applications with supporting documents and a means-assessment scorecard; approval workflow with committee sign-off; validity periods and renewal review; **sponsor-funded awards where the discount is actually an external party's liability, invoiced to that sponsor** rather than written off; budget envelopes per scholarship scheme with utilisation tracking and over-commitment blocking; full GL treatment — every discount posts to a contra-revenue or expense account so the school always knows the true cost of its generosity.
**Surface.** Livewire + API (application submission).
**Depends on.** FIN-02, PPL-03.

#### FIN-08 · Procurement, Suppliers & Accounts Payable 🇿🇼
**Purpose.** Control spending before it happens, not after.
**Capabilities.** Supplier master with banking details, contacts, categories, performance ratings; 🇿🇼 **ITF263 tax clearance certificate tracking with expiry alerts, and automatic application of 10% withholding tax on payments to suppliers without a valid clearance**; requisition raising by any department with budget checking at the point of request; quotation solicitation and side-by-side comparison; approval chains by amount threshold (CORE-07); purchase order generation and dispatch; goods received notes with partial delivery and quality rejection; **three-way match (PO ↔ GRN ↔ invoice) with tolerance rules and exception flagging**; supplier invoice registration and approval; payment scheduling, batching, and remittance advice; supplier aging and statement reconciliation; contract and service agreement register with renewal alerts; procurement spend analytics by category and supplier.
**Surface.** Livewire + API (mobile requisition and approval).
**Depends on.** FIN-01, CORE-07.

#### FIN-09 · Inventory, Stores & Requisitions
**Purpose.** Know what the school owns, where it is, and charge it correctly when it is consumed.
**Capabilities.** Multi-store structure (main store, kitchen store, boarding store, laboratory, farm, maintenance, uniform shop) with per-store custodians; item master with categories, units of measure, reorder levels, and expiry tracking; **FIFO costing** with weighted-average as a configurable alternative; goods receipt from procurement; internal requisition and issue workflow with authorisation; **automatic expense recognition — the moment stock is issued for use, the cost posts to the requesting cost centre's expense account, exactly as your original specification required**; inter-store transfers; stock take with count sheets, variance analysis, and approved adjustment; shrinkage reporting; low-stock and expiry alerts; batch and lot tracking for perishables; uniform and textbook sales stock linked to the learner fee account.
**Surface.** Livewire + API (mobile stock take and requisition).
**Depends on.** FIN-01, FIN-08.

#### FIN-10 · Fixed Assets & Depreciation
**Purpose.** Maintain a defensible asset register and keep the balance sheet honest.
**Capabilities.** Asset register with categories, acquisition cost, date, source, location, custodian, serial numbers, photographs, and QR/barcode tags; capitalisation from procurement; **depreciation schedules — straight line, reducing balance, units of production — with automated monthly or annual GL posting**; revaluation and impairment; asset transfer between departments and locations; maintenance history linked to OPS-02; disposal and write-off with gain/loss calculation and approval; **physical verification cycles with scan-based audit and missing-asset exception reports**; insurance register with cover values and renewal alerts; asset-class reporting for the balance sheet and for insurance schedules.
**Surface.** Livewire + API (mobile asset verification).
**Depends on.** FIN-01, FIN-08.

#### FIN-11 · Budgeting, Forecasting & Cost Centres
**Purpose.** Plan the year and see the variance before it becomes a crisis.
**Capabilities.** Annual and per-term budgets by cost centre and account; bottom-up departmental submission with consolidation and approval; **budget versus actual variance reporting with drill-down**, live, not month-end; **commitment accounting — an approved purchase order consumes budget immediately, so a department cannot over-commit while invoices are in transit**; budget checking enforced at requisition; virement (transfer between budget lines) with approval; fee income forecasting from enrolment projections and historical collection rates; cash flow forecasting from invoice due dates and payment behaviour; multi-year comparison; scenario modelling (what happens to the surplus at 85% collection instead of 95%).
**Surface.** Livewire.
**Depends on.** FIN-01, FIN-08.

#### FIN-12 · Financial Reporting & Period Close ⭐
**Purpose.** Produce statements the school's auditors accept, for any period, forever.
**Capabilities.** **Core statements generated live from the GL** — trial balance, income statement, balance sheet, cash flow, all filterable by date range, term, academic year, cost centre, section, and currency, and always reconstructable for closed periods; fee collection analysis by component, class, section, and residency; debtor and creditor aging; **income and expenditure by term with prior-term comparatives** — the report your requirement centres on; departmental profitability (does the farm pay for itself, is the bus route viable, is the tuckshop profitable); bank and cash position; **period close checklist workflow** with mandatory pre-close validations (trial balance balances, suspense cleared, till sessions closed, gateway reconciliation complete, FX revaluation posted); **prior-period adjustment reporting so any backdated entry into a closed term is visible on its own line, never blended into current figures**; **point-in-time reporting — "show me the exact position as at 30 April 2025 as it was known on 5 May 2025"**, which is what defeats retrospective tampering claims; scheduled report delivery to the head and board; export to Excel, PDF, and accounting-package formats (QuickBooks/Sage/Pastel).
**Surface.** Livewire + API (summary dashboards).
**Depends on.** FIN-01, all other FIN modules.

#### FIN-13 · ZIMRA Fiscalisation (FDMS) 🇿🇼
**Purpose.** Meet Zimbabwe's virtual fiscalisation obligation for the school's commercial transactions.
**Regulatory context.** ZIMRA's Fiscalisation Data Management System permits **virtual fiscalisation via direct server-to-server API integration** between a taxpayer's accounting, POS, or invoicing system and ZIMRA — the route explicitly recommended for medium-to-large taxpayers running centralised systems (Public Notice 26 of 2024; SI 104 of 2010; Public Notice 50 of 2023). Official API documentation is obtained from ZIMRA's downloads portal.
**Capabilities.** Device registration lifecycle — CSR generation, certificate issuance, certificate renewal before expiry with alerting; **fiscal day management (open day → transact → close day)** with correct counter handling; receipt submission for **fiscal invoices, credit notes, and debit notes** with the required hash and signature; **per-currency counters (USD and ZWG) maintained separately**; tax type handling (standard-rated, zero-rated, exempt, withholding); **fiscalisation routing engine — a configurable rule set determining which transactions are fiscalised**, since core tuition is generally not a commercial supply while tuckshop sales, uniform sales, hall and bus hire, and non-exempt levies are; **offline resilience — receipts queue locally when FDMS is unreachable and submit automatically on reconnection**, which is mandatory given Zimbabwean connectivity; QR code and verification code rendering onto the printed receipt; ping/status monitoring with a health indicator; **automated Z-report / day-close submission** on schedule; submission failure alerting with a retry workbench; complete fiscal audit log; per-school device configuration so a group with multiple registered entities can operate multiple devices.
**Surface.** Livewire (config, monitoring, retry queue).
**Depends on.** FIN-04, CORE-12.
**Risk note for the build.** Treat the ZIMRA integration as an isolated adapter behind an interface, with a full sandbox test suite. It is the module most likely to change under you, and it must never be able to block a receipt from being taken — money is receipted first, fiscalised asynchronously, and reconciled.

#### FIN-14 · Student Wallet, Tuckshop POS & Cashless Campus
**Purpose.** Replace cash in boarders' hands with a controlled account — a genuine differentiator for boarding schools and a real revenue line.
**Capabilities.** Prepaid wallet per learner, funded by parents through the portal, app, or bursary counter; **parent-set spending controls — daily and weekly limits, blocked categories, low-balance alerts**; RFID card, QR, or biometric identification at point of sale; tuckshop POS terminal (touch-optimised Livewire, functional offline with sync); product catalogue and stock integration with FIN-09; per-learner transaction history visible to parents in near real time; other campus spend points (canteen, stationery shop, printing, laundry); wallet-to-fee-account transfers; **term-end balance handling — refund, carry forward, or transfer to fees, per school policy**; 🇿🇼 automatic fiscalisation of tuckshop sales through FIN-13; wallet reconciliation and float control.
**Surface.** Livewire (POS, admin) + API (parent top-up and monitoring, learner balance).
**Depends on.** FIN-04, FIN-09, FIN-13.

---

### Domain E · Boarding & Welfare (`BRD`)

> This domain is what makes the product sellable to Zimbabwe's premier boarding schools, and it is where generic international school systems are weakest.

#### BRD-01 · Hostel, Room & Bed Allocation
**Purpose.** A digital twin of the physical boarding estate.
**Capabilities.** Hierarchical mapping `Hostel → Wing/Floor → Room → Bed` with capacity, condition, and facility attributes; housemaster and matron assignment per hostel; **allocation engine enforcing gender segregation, age/form-level banding, house affiliation, and medical needs** (a learner with asthma near the exit, siblings apart or together per policy); occupancy dashboard with live bed availability; waiting list for oversubscribed hostels; mid-term reallocation with full movement history; room inspection scheduling with scored records and photographs; damage recording with **automatic charging to the learner's fee account** through FIN-02; per-hostel occupancy and utilisation reporting.
**Surface.** Livewire + API (learner sees their allocation; parent sees hostel details).
**Depends on.** PPL-01, CORE-02.

#### BRD-02 · Boarding Roll Call & Movement
**Purpose.** Know where every boarder is, at all times. This is a child-safety obligation before it is an administrative one.
**Capabilities.** Configurable roll call points (morning, after lessons, prep, lights-out, weekend, ad hoc); **biometric, RFID, and QR hardware hooks plus reliable manual fallback**; mobile roll call for housemasters with **offline capture and sync** — dormitories often have no signal; instant missing-learner alert to the boarding master, deputy head, and configured contacts; movement log across campus checkpoints; sick bay, exeat, sports fixture, and detention status automatically excluded from missing counts; **discrepancy escalation ladder with timed escalation**; historical roll call audit; boarder presence reporting feeding the catering per-capita calculation in BRD-04.
**Surface.** Livewire (oversight) + API (primary — housemaster mobile).
**Depends on.** BRD-01, ACA-04, CORE-09.

#### BRD-03 · Exeat, Leave & Visitor Management
**Purpose.** Control who leaves, who arrives, and prove the chain of custody of a child.
**Capabilities.** **Parent-initiated exeat request through portal or app**; multi-stage approval per your specification and configurable per school — housemaster verification → boarding master approval → head or deputy final sign-off, with province/duration-based escalation; exeat types (weekend, half-term, medical, compassionate, sports, day pass); **printed and digital exeat pass with QR code verified at the gate**; automatic exit and re-entry timestamping; overdue return alerting to parents and staff; **collection authorisation checked against the guardian permissions in PPL-03** — the system refuses to release a child to someone not authorised, and logs the attempt; visitor register at the gate capturing identity document, contact, purpose, relationship, host, and time in/out, with visitor badge printing; blacklist and watchlist; visiting-day management with slot booking; full movement history per learner.
**Surface.** Livewire (gate terminal, approvals) + API (parent request, mobile approval, gate scanning).
**Depends on.** BRD-02, PPL-03, CORE-07.

#### BRD-04 · Catering, Menus & Kitchen Inventory
**Purpose.** Feed hundreds of boarders three times a day, at a known and controlled cost per head.
**Capabilities.** Cyclical menu planning by day and meal with seasonal variants; recipe and portion definitions; **per-capita ration calculation scaled automatically against live boarder attendance from BRD-02** — the kitchen draws for who is actually present, not for the nominal roll; store issue against the day's requirement with FIFO costing through FIN-09; 🇿🇼 staple tracking (mealie-meal, cooking oil, sugar, meat, vegetables) with wastage recording; **special dietary register — allergies, intolerances, religious exemptions, medical diets — surfaced on the serving terminal**, which is a safeguarding requirement not a nicety; meal attendance capture (card/QR) with no-show analysis; **cost-per-boarder-per-day analytics with trend and variance against budget**; integration with OPS-03 so farm-produced maize, vegetables, and meat transfer into the kitchen at internal cost; supplier ordering triggers on reorder levels.
**Surface.** Livewire (planning, kitchen terminal) + API (menu published to learners and parents).
**Depends on.** BRD-02, FIN-09, OPS-03.

#### BRD-05 · Laundry & Linen Services
**Purpose.** Track school-issued items and the laundry cycle without losing them.
**Capabilities.** Linen and school-issue asset register per learner (mattress, blankets, curtains, towels, uniform items) with condition grading at issue and return; barcode or laundry-mark tagging; laundry cycle scheduling by hostel; collection and return reconciliation with missing-item flagging; **loss and damage charging to the fee account**; end-of-term return checklist gating clearance; laundry cost tracking per hostel.
**Surface.** Livewire + API (learner sees their issued items).
**Depends on.** BRD-01, FIN-02.

#### BRD-06 · Health, Clinic & Sanatorium 🔒
**Purpose.** Run the school clinic properly. Handles the most sensitive data in the system.
**Capabilities.** Confidential medical record per learner with **strict role-gated access — nurse and doctor full, housemaster limited to actionable flags, teachers see only emergency-critical markers**; allergy, chronic condition, and disability register; immunisation record with schedule tracking; medical aid/insurance details and authorisation; sick bay admission and discharge with observation notes; consultation records; **medication administration log with dosage, time, administering staff, and parent consent** — a legal record; prescription and clinic stock control through FIN-09; injury and incident reporting with parent notification; referral to external practitioners and hospitals with transport dispatch to OPS-01; epidemic and outbreak monitoring with case clustering alerts; medical certificate management for absence; consent record for treatment; retention and access audit under CMP-03.
**Surface.** Livewire (clinical) + API (parent notification, consent, limited history).
**Depends on.** PPL-01, CORE-08, CMP-03.

#### BRD-07 · Discipline, Conduct & Behaviour
**Purpose.** A consistent, evidenced disciplinary record — protective for both the learner and the school.
**Capabilities.** Merit and demerit system with configurable point values and thresholds; incident logging with category, severity, location, witnesses, and evidence attachments; sanction catalogue (warning, detention, community service, suspension, exclusion) with automatic trigger rules on point thresholds; detention scheduling, register, and attendance; **suspension and expulsion workflow with approval chain, disciplinary committee record, parent notification, and appeal tracking**; behaviour trend analytics per learner, class, house, and hostel; **positive behaviour and commendation recording** so the record is not purely punitive; conduct grade feeding the report card; prefect and student leadership register with delegated limited permissions; parent visibility of their own child's record through the portal.
**Surface.** Livewire + API (staff logging, parent viewing).
**Depends on.** PPL-01, CORE-07, CORE-09.

#### BRD-08 · Counselling & Safeguarding 🔒
**Purpose.** Child protection, held to a higher confidentiality standard than anything else in the system.
**Capabilities.** Counselling session records visible **only** to the counsellor, chaplain, and designated safeguarding lead — invisible to general administration, including the head unless explicitly configured; **safeguarding concern reporting with a restricted-access chain of custody**; welfare monitoring for identified vulnerable learners (orphans, child-headed households, learners on bursary hardship); referral tracking to external agencies; risk assessment records; **separate, hardened audit trail — every access to a safeguarding record is logged and reviewable**; anonymous reporting channel for learners; case review scheduling.
**Surface.** Livewire (restricted) + API (anonymous learner reporting only).
**Depends on.** PPL-01, CORE-08.
**Design note.** This module's access model is inverted relative to the rest of the system: Super Admin does **not** get automatic access. Access is explicitly granted, individually, and every read is logged.

---

### Domain F · Operations & Estates (`OPS`)

#### OPS-01 · Transport & Fleet Management 🇿🇼
**Purpose.** Move learners safely and know what the fleet costs.
**Capabilities.** Vehicle register (buses, minibuses, trucks, tractors, staff vehicles) with specifications, capacity, and documents; driver register with licence class, defensive-driving certification, medical, and expiry alerting; route definition with stops, GPS coordinates, distances, and timings; **learner-to-route-and-stop assignment with zone-based fee calculation feeding FIN-02**; **passenger manifest generation per trip, per direction, printable and available on the driver's phone**; boarding and alighting capture (card/QR) with parent notification on pickup and drop-off; trip scheduling for routine runs, sports fixtures, excursions, and medical dispatch; fuel logging with consumption analysis and anomaly detection (a common theft vector); mileage-based and time-based service scheduling; 🇿🇼 **compliance renewal alerts — vehicle licence, ZINARA, certificate of fitness, insurance, route permit, passenger insurance**; incident and accident register; per-vehicle and per-route cost centre reporting so the school knows whether a route is viable; optional GPS telemetry integration.
**Surface.** Livewire + API (driver manifest, parent tracking notifications).
**Depends on.** PPL-01, FIN-02, FIN-10.

#### OPS-02 · Maintenance & Works Management
**Purpose.** Keep the estate running and stop maintenance spend disappearing into "sundries".
**Capabilities.** Fault reporting from any user via web or mobile with photograph and location; job card creation, assignment, and priority; **preventive maintenance schedules** for generators, boreholes, water pumps, solar systems, kitchen equipment, and buildings; contractor and in-house team management; parts issue from stores through FIN-09; **labour and material cost capture per job with GL posting to the correct cost centre**; asset maintenance history linked to FIN-10; SLA tracking on response and completion; outstanding works dashboard; capital works project tracking with budget and milestones.
**Surface.** Livewire + API (fault reporting, technician job cards).
**Depends on.** FIN-09, FIN-10.

#### OPS-03 · Estates, Farm & Production Units 🇿🇼
**Purpose.** Many large Zimbabwean boarding schools operate working farms, gardens, orchards, and livestock that feed the kitchen. This is a genuine profit centre and almost no competing product models it. It is a differentiator.
**Capabilities.** Land parcel and field register with hectarage and soil records; crop planning by season with planting and harvest calendars; input tracking (seed, fertiliser, chemicals, fuel, labour) costed to the crop; yield recording and cost-per-unit calculation; livestock register with herd/flock numbers, births, deaths, sales, feed, veterinary treatment, and dip records; poultry and egg production cycles; irrigation and borehole management; **internal transfer of produce to the school kitchen at cost, posting a real journal** so the farm's contribution and the catering department's true cost are both visible; external sales of surplus with fiscalised invoicing; farm labour management; **per-unit profitability reporting — does the farm actually save money, or is it a subsidy?**
**Surface.** Livewire + API (field recording on mobile).
**Depends on.** FIN-01, FIN-09, BRD-04.

#### OPS-04 · Utilities & Energy Management 🇿🇼
**Purpose.** In a load-shedding economy, energy is a top-three operating cost and a top-three source of unbudgeted spend.
**Capabilities.** Utility account register (ZESA, municipal water, refuse); **prepaid electricity token purchase and consumption tracking per meter**; meter reading capture with consumption trend and anomaly alerting; generator run-hour logging with diesel consumption, cost per hour, and service scheduling; **solar system monitoring and generation logging**; borehole and water storage monitoring; **per-department and per-building utility cost allocation** so boarding, the farm, and the academic block each carry their real cost; load-shedding schedule tracking with impact reporting; consumption benchmarking term-on-term and year-on-year; budget variance alerts on utilities.
**Surface.** Livewire + API (meter reading capture).
**Depends on.** FIN-01, FIN-09.

#### OPS-05 · Facilities Booking & External Hire
**Purpose.** Stop double-booking the hall, and turn idle facilities into revenue.
**Capabilities.** Bookable resource register (halls, laboratories, sports fields, pool, boardroom, ICT lab, buses) with capacity and equipment; internal booking with clash detection against the timetable in ACA-03; **external hire with quotation, contract, deposit, fiscalised invoicing, and damage deposit handling**; recurring bookings; setup and cleaning task generation into OPS-02; utilisation reporting; hire revenue by facility.
**Surface.** Livewire + API (staff booking requests).
**Depends on.** ACA-03, FIN-03, FIN-13.

#### OPS-06 · Security, Gate & Access Control
**Purpose.** Control the perimeter and hold an incident record.
**Capabilities.** Gate terminal (shared with BRD-03) for learners, staff, visitors, contractors, suppliers, and vehicles; contractor induction and site-access authorisation with document verification; security patrol scheduling with checkpoint scanning; incident and occurrence book — digital, timestamped, non-editable; CCTV reference logging; lost property register; key and access-card issue and return; emergency muster roll generation — **a live list of everyone on site, per building, for fire drills and real emergencies**; drill records.
**Surface.** Livewire (gate, control room) + API (patrol scanning).
**Depends on.** BRD-03, CORE-08.

#### OPS-07 · Sports, Houses & Co-curricular
**Purpose.** The house system and sport are central to identity at Zimbabwean boarding schools, and parents care about them intensely.
**Capabilities.** House registry with membership, house masters, and captains; **inter-house competition scoring across sport, academics, culture, and conduct, with a live house leaderboard**; sports and club registry with membership and per-activity fee linkage to FIN-02; team selection and squad management; fixture scheduling with venue booking (OPS-05) and transport requisition (OPS-01); results capture and standings; **colours, awards, and honours register** feeding the report card and the alumni record; training attendance; equipment issue through FIN-09; galas, festivals, and speech-day event management; parent notification of fixtures, selections, and results.
**Surface.** Livewire + API (fixtures, results, selections to parents and learners).
**Depends on.** PPL-01, ACA-04, OPS-01, OPS-05.

---

### Domain G · Communication & Engagement (`COM`)

#### COM-01 · Messaging Gateways & Delivery 🇿🇼
**Purpose.** Reach parents on the channel they actually read, at a cost the school can control.
**Capabilities.** **Driver-based channel abstraction** — bulk SMS aggregators (Econet, NetOne, BulkSMS Zimbabwe, Twilio, Africa's Talking), **WhatsApp Business API** (now the dominant parent channel in Zimbabwe and a first-class requirement, not an afterthought), SMTP/transactional email, Firebase push, and in-app inbox; sender ID and template registration management for WhatsApp; per-school gateway credentials; **credit balance monitoring with low-balance alerts and hard stop before the school is cut off mid-campaign**; per-message cost attribution and monthly cost reporting by module and event type; delivery receipt tracking with retry and channel fallback (WhatsApp → SMS → email); bounce and invalid-number management; opt-out registry; message log with search.
**Surface.** Livewire (config, log, cost) + API (in-app inbox).
**Depends on.** CORE-09.

#### COM-02 · Event-Driven Automation Rules
**Purpose.** Turn system events into the right message to the right person without anyone remembering to send it.
**Capabilities.** Visual rule builder — **event → condition → audience → channel → template → timing**; the standard triggers from your specification and beyond: payment received (instant receipt confirmation), invoice issued, balance overdue at N days, unexplained absence at the registration cut-off, results published, report card released, exeat approved, learner arrived at school or boarded a bus, sick bay admission, disciplinary incident, low wallet balance, document expiring, birthday; audience resolution rules (fee-responsible guardian only, all guardians, primary contact, learner, class teacher); scheduling with quiet hours and batching; throttling to prevent notification fatigue; per-rule cost estimation before activation; A/B template testing; rule execution log.
**Surface.** Livewire.
**Depends on.** COM-01, CORE-09.

#### COM-03 · Parent Portal & Mobile Services
**Purpose.** The API surface behind the Next.js parent portal and the Flutter app. For most parents, **this is the product** — it is where perceived value lives.
**Capabilities.** Multi-child dashboard with a single sign-in; **live fee balance, statement, and invoice download**; in-app payment initiation through FIN-05 with instant receipt; academic dashboard — attendance, continuous assessment, exam results, report cards, subject teacher remarks, progress trends across terms; timetable; homework and assignment status with non-submission alerts; **exeat request and authorisation**; permission slips and consent forms with digital signature; sick bay and health notifications; disciplinary notifications; wallet top-up and spend monitoring; transport tracking notifications; school calendar and events; direct messaging to class teacher and administration with routing rules; document downloads; profile and contact self-update with approval; notification preference management.
**Surface.** API only (consumed by Next.js and Flutter).
**Depends on.** PPL-03, FIN-03, ACA-05, BRD-03.

#### COM-04 · Learner Portal & Mobile Services
**Purpose.** The learner-facing API surface, with age-appropriate scoping.
**Capabilities.** Personal timetable; assignments with submission; LMS content with offline download; CBT delivery; results and report cards (release-gated); attendance record; library loans and catalogue search; wallet balance and transaction history; SBP portfolio submission; club and team memberships and fixtures; school notices; **anonymous safeguarding reporting channel**; **strict age-appropriate feature gating — a Grade 2 learner's app is not a Form 6 learner's app**, controlled by configuration per level.
**Surface.** API only.
**Depends on.** ACA-08, ACA-09, FIN-14.

#### COM-05 · Staff & Teacher Portal Services
**Purpose.** The API surface for teaching and non-admin staff on Next.js and mobile — the people who use the system most often and have the least patience for friction.
**Capabilities.** Personal timetable and cover duties; **attendance marking with offline queue**; **mark entry with draft and submit**; class and learner lists with photographs; lesson plan and scheme submission; homework setting and marking; SBP moderation; learner pastoral notes; disciplinary incident logging; duty rosters; leave request and balance; payslip access; requisition raising and approval; internal messaging and staff announcements; document access.
**Surface.** API only.
**Depends on.** PPL-04, ACA-04, ACA-05.

#### COM-06 · Calendar, Events & Notice Board
**Purpose.** One authoritative school calendar, published everywhere.
**Capabilities.** Unified calendar aggregating term dates, examinations, fixtures, holidays, meetings, visiting days, and deadlines; audience-targeted events (whole school, section, class, house, staff, parents); event RSVP and ticketing with payment where applicable; notice board with priority, pinning, expiry, and read receipts; newsletter composition and distribution with per-issue archive; **iCal feed and calendar subscription** so parents get school dates in their phone calendar; push reminders ahead of key dates.
**Surface.** Livewire (authoring) + API (consumption).
**Depends on.** CORE-03, COM-01.

#### COM-07 · Virtual Meetings & Online Classes
**Purpose.** Online lessons and remote parent consultations.
**Capabilities.** **Zoom Server-to-Server OAuth and Google Meet integration** for programmatic meeting creation; scheduled online lessons generated from the timetable; **encrypted meeting credentials distributed to the correct dashboards only** — never posted publicly; parent–teacher consultation booking with per-teacher slot management, which is enormously valuable for diaspora parents; recording management with retention and access policy; **attendance capture from Zoom webhooks — join and leave timestamps scored into the attendance record**; waiting room and admission control; session log.
**Surface.** Livewire (setup) + API (join links, booking).
**Depends on.** ACA-03, ACA-04.

#### COM-08 · Feedback, Surveys & Complaints
**Purpose.** Structured listening, and a defensible complaints record.
**Capabilities.** Survey builder with question types, logic branching, and anonymity options; audience targeting; distribution across portal, SMS, and WhatsApp; response analytics; complaint and suggestion intake with categorisation, assignment, SLA, resolution tracking, and escalation; parent satisfaction measurement per term; exit interview capture for withdrawing families — **why families leave is the single most commercially valuable dataset a school owns**.
**Surface.** Livewire + API.
**Depends on.** COM-01, CORE-07.

---

### Domain H · Compliance & Statutory (`CMP`)

#### CMP-01 · ZIMSEC Candidate Registration & Results 🇿🇼
**Purpose.** Remove the annual agony of national examination registration.
**Context.** ZIMSEC operates an **Online Candidate Registration System** through which authorised school personnel capture and submit candidate bio-data and subject entries for an examination session, and an **online results distribution portal** keyed on the school's centre number.
**Capabilities.** Candidate identification per session (Grade 7, O-Level, A-Level) with eligibility validation; **bio-data completeness checking against ZIMSEC requirements before submission**, catching the missing birth certificate numbers and name mismatches that cause rejections; subject entry management validated against ZIMSEC subject codes and the pathway/subject-count rules in ACA-01; **export in the exact format required by the ZIMSEC registration system**, with a validation report; entry fee calculation, invoicing to parents, and reconciliation of collections against the amount remitted to ZIMSEC — a frequent source of shortfalls; statement of entry distribution and confirmation tracking; centre number and centre details management; **results import and mapping back onto learner records** with pass-rate analysis by subject, teacher, and class; results verification requests.
**Surface.** Livewire.
**Depends on.** ACA-07, FIN-03.

#### CMP-02 · MoPSE Returns & EMIS Reporting 🇿🇼
**Purpose.** Produce the statutory returns the Ministry demands, from data the school already keeps.
**Capabilities.** **Annual Schools Census / EMIS data pack generation** — enrolment by level and gender, learner characteristics, staffing establishment and qualifications, infrastructure, and facilities; term enrolment returns; staff establishment returns; **inspection readiness pack** assembling attendance registers, staff records, and required documentation on demand; district and provincial reporting formats; per-return submission tracking with deadlines and evidence of submission; data quality validation before export, flagging missing national registration numbers and incomplete records that would fail Ministry checks.
**Surface.** Livewire.
**Depends on.** PPL-01, PPL-04, CORE-02.

#### CMP-03 · Data Protection, Consent & Privacy 🇿🇼
**Purpose.** Comply with Zimbabwe's **Cyber and Data Protection Act [Chapter 12:07]**, which matters more here than in most sectors because the data subjects are children.
**Capabilities.** Consent registry — what each guardian consented to, when, and for what purpose (photography, publication, medical treatment, trip participation, data sharing, marketing), with withdrawal handling; **data subject access request workflow** with identity verification, compilation, and delivery within the statutory window; correction and erasure request handling with lawful-basis assessment; **retention schedule per record class with automated review and disposal queues** — academic records retained long, CCTV briefly, marketing consent-bound; field-level encryption for sensitive categories (medical, safeguarding, national identifiers); **breach detection, logging, and notification workflow**; processing register documenting purpose and lawful basis per data category; third-party processor register (gateways, SMS providers, cloud storage) with data-sharing agreements; privacy notice versioning and acceptance tracking; **special protections for minors' data**, including restrictions on what the learner portal exposes by age.
**Surface.** Livewire.
**Depends on.** CORE-08, CORE-10.

#### CMP-04 · Policy, Document Register & Retention
**Purpose.** The school's own governance paperwork, versioned and acknowledged.
**Capabilities.** Policy repository with version control, effective dates, and review cycles; **acknowledgement tracking — which staff and parents have read and accepted which version**; statutory document register (registration certificate, licences, insurance, tax clearance, health inspections) with expiry alerting; board and committee minute repository with access control; contract register; incident register consolidation; retention rules aligned to CMP-03; document search across the estate.
**Surface.** Livewire + API (policy acknowledgement).
**Depends on.** CORE-10, CMP-03.

---

### Domain I · Intelligence & Integration (`INT`)

#### INT-01 · Reporting Engine & Data Warehouse
**Purpose.** Answer questions nobody anticipated at build time, without a developer.
**Capabilities.** Report library across all domains; **custom report builder with field selection, filtering, grouping, aggregation, and calculated columns**, permission-scoped so a user can only build reports over data they may already see; saved reports with sharing; scheduled report delivery by email; export to Excel, PDF, and CSV; **nightly denormalised reporting tables** so heavy analytics never contend with transactional load; cross-term and cross-year comparative reporting; **cross-school consolidated reporting for group tenants**; report execution audit.
**Surface.** Livewire + API (report retrieval).
**Depends on.** All domains.

#### INT-02 · Executive Dashboards
**Purpose.** The head's and bursar's morning screen.
**Capabilities.** Role-based dashboards assembled from a widget library — enrolment and headcount, attendance today, fee collection against target, cash and bank position by currency, aged debtors, outstanding approvals, boarding occupancy, staff on leave, maintenance backlog, examination results trends, notification spend; drill-down from any widget to the underlying records; **daily digest to the head by email or WhatsApp**; board reporting pack generation; period-over-period comparatives; configurable KPI targets with variance highlighting.
**Surface.** Livewire + API (mobile executive view).
**Depends on.** INT-01.

#### INT-03 · Early Warning & Predictive Analytics
**Purpose.** Flag problems while they are still cheap to fix.
**Capabilities.** **At-risk learner identification** combining attendance decline, mark trajectory, disciplinary frequency, fee arrears, and clinic visits into a composite indicator with an explainable breakdown — never a black-box score; **fee default risk prediction** from payment history and behaviour, driving proactive engagement rather than end-of-term crisis; enrolment forecasting and attrition risk by cohort; **withdrawal risk flagging** so the school can intervene before a family leaves; teacher workload and burnout indicators; inventory and consumption anomaly detection (fuel, food, stores) as a theft signal; capacity planning projections.
**Surface.** Livewire + API (intervention lists for pastoral staff).
**Depends on.** INT-01.
**Ethical constraint.** Every flag is explainable and advisory. No automated adverse decision is taken about a child without a human in the loop, and risk scores are never exposed to learners or parents.

#### INT-04 · Public API, Webhooks & Integrations
**Purpose.** Let the platform participate in a wider ecosystem — and become harder to displace.
**Capabilities.** Documented, versioned public REST API with OpenAPI specification and generated client SDKs; API key management with scoped abilities, rate limiting, and usage analytics; **outbound webhooks** for key events so schools can build their own automations; accounting package export (QuickBooks, Sage, Pastel); Google Workspace and Microsoft 365 provisioning and single sign-on; calendar sync; biometric and RFID hardware adapters; SMS and payment gateway driver SDK so a new Zimbabwean provider can be added without core changes; **bulk data export for contract exit** — a commercially honest feature that increases trust at the point of sale.
**Surface.** API + Livewire (key management).
**Depends on.** CORE-05.

---

### Domain J · Commercial & SaaS Control (`SAA`)

> Vendor-side modules. These are how you get paid and how you support the product at scale. They are invisible to schools.

#### SAA-01 · Licensing, Subscription & Entitlement
**Purpose.** Monetise. Without this, every other module is a hobby.
**Capabilities.** Plan definition with module bundles, learner-count bands, and seat limits; subscription lifecycle (trial → active → past due → grace → suspended → cancelled); **automatic module entitlement synchronisation to `school_modules`**; usage metering against plan limits (learners, storage, messages, API calls) with soft warnings before hard enforcement; tenant invoicing and payment collection in USD and ZiG through the same gateway layer; **graceful degradation on non-payment — read-only access with a clear notice, never sudden data denial**, because a school locked out mid-term will never renew and will tell every other school; upgrade, downgrade, and proration; licence key validation for on-premise deployments with offline grace periods; renewal and churn tracking.
**Surface.** Livewire (vendor console) + limited API.
**Depends on.** CORE-04.

#### SAA-02 · Vendor Control Centre
**Purpose.** Operate the platform across all tenants.
**Capabilities.** Cross-tenant tenant and school registry with health status; **audited, time-boxed, consent-gated support impersonation**; global system health, queue, and error monitoring; per-tenant resource consumption; feature flag rollout control (enable a new module for one pilot tenant, then a cohort, then everyone); release and migration management with per-tenant staging; broadcast announcements to all tenants; incident status page management.
**Surface.** Livewire (vendor only, separate authentication realm, IP-restricted).
**Depends on.** CORE-12, SAA-01.

#### SAA-03 · Onboarding, Support & Customer Success
**Purpose.** Getting a school live is the hardest part of this business. Systematise it.
**Capabilities.** **Onboarding checklist and progress tracker per school** — data import, configuration, fee structure setup, training, go-live sign-off; configuration templates so a new school clones a proven setup rather than starting empty; in-app support ticketing with priority and SLA; embedded knowledge base and contextual help; guided product tours for first-time users; training material library and completion tracking; **adoption analytics — which modules a school actually uses**, which is the leading indicator of renewal; health scoring and churn-risk alerting; release note distribution.
**Surface.** Livewire + API (in-app help and ticketing).
**Depends on.** SAA-01.

---

## Part 7 — ⭐ The Financial Integrity Charter

> Your requirement, stated plainly: *"the finances reports must be handled very well and reports without missing funds from last terms."*
>
> That is not a feature. It is an architectural property, and it must be designed in from the first migration. This charter defines the twelve controls that make it structurally true, and every finance module is audited against them before it merges.

### Control 1 — The ledger is the only source of truth
No balance is ever stored in a mutable column. Every balance is `SUM(debits) − SUM(credits)` over immutable journal lines, filtered by date. A cached balance may exist for performance, but it is rebuildable at any moment from the journals, and a nightly job verifies that every cached figure matches its derivation.

### Control 2 — Append-only, forever
`UPDATE` and `DELETE` privileges are revoked on journal tables at the database user level. Not by convention — by permission. Corrections are new, linked, reversing entries. The original always remains visible.

### Control 3 — Every event is double-entry
Invoice, receipt, credit note, discount, write-off, refund, expense, stock issue, payroll, depreciation, FX revaluation, internal transfer — all of them post balanced journals. If a module wants to move money, it posts a journal. There is no other mechanism.

### Control 4 — Dual timestamps make backdating visible
Every journal carries `effective_at` (the date it applies to) and `posted_at` (the date it was entered). A receipt entered in July for a March payment appears in March's figures **and** on the prior-period adjustment report. It can never quietly change a signed-off term.

### Control 5 — Money is an integer with a currency
No floats. No currency-less amounts. Ever. Enforced by a `Money` value object and a static analysis rule.

### Control 6 — Every conversion stores its rate
An amount converted between currencies stores the rate, the rate source, the rate date, and both the original and converted values. The conversion can always be re-derived and challenged.

### Control 7 — FX differences are posted, never absorbed
When a ZiG payment settles a USD invoice at a rate different from the invoice's, the difference posts to Realised FX Gain/Loss. At period close, open foreign-currency balances revalue to Unrealised FX Gain/Loss. **This single control is the difference between a bursar trusting your product and abandoning it.**

### Control 8 — Money is on the books the moment it arrives
Unidentified deposits are receipted into a Suspense account immediately, then allocated when identified. Money is never held off-ledger while somebody works out whose it is. The suspense balance is a monitored KPI and must be cleared before a period can close.

### Control 9 — Cash is controlled by till sessions
No cashier transacts outside an open till session. Every session has a declared opening float and a **blind close** — the cashier counts before the system reveals the expected figure. Variances are recorded, explained, and reported. This is the standard retail control, and school bursaries need it more than shops do.

### Control 10 — Document numbering is gapless
Sequences are allocated transactionally with row locking. A number consumed by a failed transaction is recorded as void with a reason. A gap in a receipt series is an audit finding, and this system will never produce one.

### Control 11 — Period close is a validated gate, not a button
A period cannot close until: trial balance balances; suspense is cleared or explicitly acknowledged; every till session is closed; gateway and bank reconciliation is complete; FX revaluation is posted; the roll-over reconciliation invariant holds. Any failure blocks the close and produces an exception report naming the specific items.

### Control 12 — Reopening a closed period is a deliberate, witnessed act
Two named approvers. A mandatory reason. An immutable audit entry. An email to the head. And every transaction posted into a reopened period is permanently tagged as a prior-period adjustment.

### The four automated guardians

| Job | Frequency | Action on failure |
|---|---|---|
| Trial balance assertion (debits = credits, per school, per currency) | Nightly | Page the vendor ops team; flag the school's finance dashboard |
| Cached-balance versus derived-balance verification | Nightly | Rebuild the cache; log the discrepancy for investigation |
| Gateway ↔ receipt ↔ bank ↔ GL four-way reconciliation | Daily | Produce an exception list for the bursar; never auto-resolve |
| Term-boundary carry-forward invariant | On roll-over + weekly | Block the roll-over; produce a variance report |

---

## Part 8 — ⭐ The Fee & Billing Model

> Your requirement: *"full time students and part time students so their billings should differ since part time students are charged per subject as the full time are charged a once off."*
>
> This is modelled generically rather than as a special case, because the moment you hard-code two billing modes, the third one arrives.

### 8.1 The billing pipeline

```
  Learner              Fee Structure Rules            Charge Lines
  ────────             ───────────────────            ────────────
  enrolment_type  ──┐
  residency       ──┤
  level / section ──┼──► RULE MATCHING ──► COMPONENT ──► BILLING BASIS ──► amount
  pathway         ──┤    (priority order)   SELECTION      EVALUATION
  house           ──┤          │
  nationality     ──┤          │                             │
  custom fields   ──┘          ▼                             ▼
                       resolution trace              DISCOUNT ENGINE
                       (auditable: "why                      │
                        was this learner                     ▼
                        billed this?")               PRORATION ENGINE
                                                             │
                                                             ▼
                                                   INVOICE (multi-currency)
                                                             │
                                                             ▼
                                                     GL JOURNAL POSTING
```

### 8.2 Billing bases

| Basis | Formula | Typical use |
|---|---|---|
| `FLAT_PER_TERM` | fixed amount | **Full-time tuition, levies, boarding** |
| `PER_SUBJECT` | `Σ(subject_rate)` over enrolled subjects | **Part-time tuition** |
| `PER_MONTH` | `rate × months in term` | Some transport and boarding arrangements |
| `PER_DAY` | `rate × attended/enrolled days` | Weekly boarding, casual arrangements |
| `PER_UNIT` | `rate × quantity` | Textbooks, uniform items, trips |
| `ONE_OFF` | fixed, charged once ever | Admission fee, development fund, refundable deposit |
| `USAGE_BASED` | metered consumption | Transport zone, wallet, printing |
| `TIERED` | banded rate table | Volume subject discounts, sibling bands |

### 8.3 Full-time versus part-time in practice

**Full-time learner — Form 3, boarder, 2026 Term 1**

| Component | Basis | Currency | Amount |
|---|---|---|---|
| Tuition (composite, any subject count) | `FLAT_PER_TERM` | USD | 450.00 |
| Development levy | `FLAT_PER_TERM` | ZWG | 1,200.00 |
| Boarding | `FLAT_PER_TERM` | USD | 600.00 |
| Sports & activities | `FLAT_PER_TERM` | USD | 25.00 |
| **Invoice** | | | **USD 1,075.00 + ZWG 1,200.00** |

**Part-time learner — A-Level, day, 3 subjects, 2026 Term 1**

| Component | Basis | Detail | Amount |
|---|---|---|---|
| Tuition — Mathematics | `PER_SUBJECT` | Sciences rate | USD 90.00 |
| Tuition — Physics | `PER_SUBJECT` | Sciences rate (practical loading) | USD 110.00 |
| Tuition — Accounting | `PER_SUBJECT` | Commercials rate | USD 80.00 |
| Registration | `ONE_OFF` | first term only | USD 20.00 |
| **Invoice** | | | **USD 300.00** |

If that learner adds Statistics in week 4 of a 13-week term, the system automatically raises a pro-rated charge of `rate × 10/13`, posts it to the ledger, notifies the guardian, and records the effective date. If they drop a subject, it raises a pro-rated credit note under the same rules. **The subject enrolment record in ACA-02 is the authoritative driver — there is no separate place to maintain "how many subjects is this learner paying for."**

### 8.4 Rules that must be configurable, not coded

Per-subject rates that differ by subject group · practical and laboratory loadings · minimum charge floors · maximum caps · volume bands (cheaper from the fifth subject) · repeat-subject rates · examination-entry charges per subject · resit rates · a part-time learner who is also a boarder · a full-time learner taking one extra subject outside the standard package · mid-term conversion between full-time and part-time, pro-rated across every affected component.

### 8.5 Allocation, discounts and liability

- **Split liability.** A parent pays tuition, an employer pays the levy, an NGO pays boarding. Each responsible party receives their own invoice and statement. The learner's overall position is the sum. Payments allocate against the payer's own liability, not the learner's total.
- **Allocation priority** is configurable — oldest invoice first, or by component priority (tuition before levy before extras), or manual, with the choice recorded on every receipt.
- **Discounts are contra-revenue postings**, never reduced invoice amounts. The school always sees gross fees billed, total discount granted, and net expected — which is what the board asks for.
- **Sponsor-funded awards** create a real receivable against the sponsor, not a write-off.

### 8.6 The billing safety rule
**Nothing is invoiced without a human approving a preview.** The batch run produces a full simulation — every learner, every component, every amount, with variance against the previous term and a flagged exception list (learners with no matching rule, unusually large changes, zero-value invoices). Only after explicit approval are invoices committed and journals posted. This one control prevents the catastrophic scenario of 1,400 wrong invoices reaching parents simultaneously.

---

## Part 9 — API & Frontend Contract

### 9.1 Standards

| Aspect | Standard |
|---|---|
| Base | `https://{tenant}.domain.co.zw/api/v1/` |
| Versioning | URL path. `v1` is supported for a minimum of 12 months after `v2` ships. |
| Auth | `Authorization: Bearer {token}` (Sanctum) |
| School context | Resolved from the token; `X-School-Id` header permitted only for multi-school users, and validated against their assignments |
| Session context | `X-Academic-Year-Id` and `X-Term-Id` headers, defaulting to the user's current session |
| Format | JSON. `snake_case` keys. ISO-8601 UTC timestamps. |
| Money | `{ "amount_minor": 45000, "currency": "USD", "formatted": "$450.00" }` — **never a bare number** |
| Pagination | Cursor-based for large collections, page-based for small ones. Default 25, maximum 100. |
| Errors | Consistent envelope with a machine-readable `code`, human `message`, and field-level `errors` |
| Rate limits | Per token, per role. Headers expose remaining quota. |
| Idempotency | `Idempotency-Key` header **required** on all payment and financial mutations |
| Localisation | `Accept-Language` — English, chiShona, isiNdebele |

### 9.2 Error envelope

```json
{
  "success": false,
  "error": {
    "code": "PERIOD_LOCKED",
    "message": "This term is closed. Contact the bursar to request a reopening.",
    "details": { "term_id": 14, "locked_at": "2026-01-15T09:22:00Z" }
  },
  "meta": { "request_id": "req_01JB...", "timestamp": "2026-09-08T10:14:22Z" }
}
```

Standard codes include `MODULE_NOT_ENABLED`, `PERIOD_LOCKED`, `INSUFFICIENT_SCOPE`, `SCHOOL_CONTEXT_REQUIRED`, `SUBSCRIPTION_SUSPENDED`, `IDEMPOTENCY_CONFLICT`, `FISCALISATION_QUEUED`.

### 9.3 Endpoint families

```
/auth/*                       login, refresh, logout, 2FA, device management
/me/*                         profile, permissions, schools, active session, preferences
/schools/{id}/...             school-scoped resources
/students/*                   profiles, results, attendance, timetable, documents
/guardians/*                  children, statements, invoices, payments, consents
/finance/*                    balances, invoices, receipts, statements, payment initiation
/academics/*                  subjects, marks, report cards, assignments, SBP
/attendance/*                 registers, marking, summaries
/boarding/*                   exeats, roll call, allocations
/communications/*             inbox, notifications, preferences
/lookups/*                    reference data, cached aggressively
```

### 9.4 Rules for the frontend teams

1. **Never assume a field exists.** Custom fields mean the shape varies by school. Render from the metadata the API supplies.
2. **Never compute money.** The API returns computed, formatted amounts. Clients display them. A client that adds two amounts together will eventually disagree with the ledger.
3. **Always send the idempotency key** on payments. Zimbabwean connectivity guarantees double-submission attempts.
4. **Design for offline.** Flutter caches read data and queues writes. Every queued write carries its idempotency key.
5. **Design for 2G.** Lazy-load, paginate, request small images. Test on a throttled connection before shipping.
6. **Respect the session context.** Display it. A parent looking at last term's results must know that is what they are seeing.

---

## Part 10 — Installation & Deployment

### 10.1 The installer flow

```
1  Welcome & licence agreement
2  Requirements check      PHP 8.4+ · extensions · Composer · Node · writable paths
3  Environment             app URL, timezone (Africa/Harare), locale, deployment mode
4  Database                credentials → connection test → create/verify schema
5  Migration               core migrations → module migrations, live progress, resumable
6  Licence activation      key validation → entitlement fetch → offline grace configuration
7  Super administrator     account creation with mandatory 2FA enrolment
8  First tenant & school   name, centre number, section structure, base currency
9  Baseline seed           Zimbabwe pack: chart of accounts, grading scales, roles,
                           learning areas, calendar template, notification templates
10 Services                mail, SMS/WhatsApp, storage, queue — each with a test button
11 Finalise                optimise, cache, write installed.lock, launch
```

Every step is resumable. A failure at step 5 does not restart at step 1.

### 10.2 Module installation model

Modules ship with the application but are **enabled per school** through entitlement. Module migrations run at deployment for all schools; the data simply stays empty for schools without the entitlement. This avoids per-tenant migration drift entirely.

`module.json` manifest declares: code, name, version, description, dependencies, provided permissions, settings schema, navigation entries, seeders, licence tier, and database tables.

### 10.3 Environments

| Environment | Purpose |
|---|---|
| Local | Docker/Sail, seeded demo school, full toolchain |
| CI | Automated Pest + PHPStan on every push; migration-from-scratch test on every merge |
| Staging | Mirrors production; anonymised data copy; where every release is verified |
| Production | Blue-green or maintenance-window deploys, pre-deploy backup, automatic rollback on health-check failure |

### 10.4 Deployment topologies

| Topology | Description | Fit |
|---|---|---|
| **Shared SaaS** | Multi-tenant, vendor-hosted, subdomain per tenant | Default for most schools |
| **Dedicated cloud** | Single-tenant instance, vendor-managed | Large groups, contractual isolation |
| **On-premise** | School's own server, licensed | Schools with connectivity or policy constraints |
| **Hybrid** | On-premise application with cloud backup and sync | Rural boarding schools with unreliable links |

---

## Part 11 — Security & Privacy Posture

| Area | Control |
|---|---|
| Transport | TLS 1.3 enforced; HSTS; secure cookie flags |
| At rest | Database encryption; field-level encryption for medical, safeguarding, and national identifiers |
| Passwords | Argon2id; configurable complexity; breach-list checking; forced rotation for privileged roles |
| 2FA | TOTP with SMS fallback; mandatory for Super Admin, Head, Bursar, Cashier |
| Sessions | Idle timeout; concurrent session limits; device register with remote revocation |
| Authorisation | Enforced in policies, not in views. Every endpoint tested for horizontal privilege escalation. |
| Tenant isolation | Global scopes plus automated tests that attempt cross-school access on every resource and must fail |
| Input | Validation on every request; parameterised queries only; output escaping; CSP headers |
| Uploads | Type and size validation; virus scanning; stored outside the web root; served through signed URLs |
| Rate limiting | Per endpoint, per user, per IP; aggressive on auth and payment endpoints |
| Secrets | Environment-based; never in the repository; rotated on staff departure |
| Audit | Every privileged action logged immutably with actor, IP, device, and session context |
| Backups | Encrypted, off-site, with **verified test restores** on a schedule |
| Child data | Minimised, purpose-bound, age-gated in the learner portal, retention-scheduled |
| Testing | Dependency scanning in CI; annual penetration test before any large-tenant onboarding |

---

## Part 12 — Build Roadmap

> Sequenced so that something demonstrable and sellable exists early, and so that no module is ever built on an unstable foundation.

| Phase | Modules | Milestone |
|---|---|---|
| **P1 · Foundation** | CORE-01 → CORE-13 | Installer runs. Multi-school. Sessions switch. Settings resolve. RBAC works. Audit captures. **Gate: the tenancy isolation test suite passes.** |
| **P2 · People** | PPL-01, PPL-03, PPL-04, CORE-11 | A school's learners, families, and staff can be imported and managed. **Gate: a real school's data imports cleanly.** |
| **P3 · Financial Core** ⭐ | FIN-01, FIN-06, FIN-02, FIN-03, FIN-04 | Double-entry ledger live. Fee structures configured. Full-time and part-time billing correct. Invoices and receipts issued. **Gate: the Financial Integrity Charter audit passes and the term roll-over invariant holds against seeded historical data.** |
| **P4 · Academic Core** | ACA-01, ACA-02, ACA-04, ACA-05 | Subjects, classes, attendance, marks, report cards. **Gate: a term's report cards generate correctly for 1,000+ learners.** |
| **P5 · API & Portals** | CORE-05 (API), COM-03, COM-04, COM-05, FIN-05 | Next.js and Flutter clients live. Parents see balances and results, and can pay. **Gate: full API contract test suite green; app usable on a throttled 2G connection.** |
| **P6 · Communication** | CORE-09, COM-01, COM-02, COM-06 | SMS, WhatsApp, push, and automation rules operating with cost control. |
| **P7 · Boarding** | BRD-01 → BRD-05 | Hostels, roll call, exeats, catering, laundry. Unlocks the premium tier. |
| **P8 · Fiscalisation & Advanced Finance** 🇿🇼 | FIN-13, FIN-07, FIN-12, FIN-14 | ZIMRA FDMS live in sandbox then production. Full statement suite. Wallet and tuckshop. |
| **P9 · Academic Depth** | ACA-03, ACA-06, ACA-07, ACA-08, ACA-09, ACA-10, ACA-11 | Timetabling, SBP, examinations, LMS, CBT, library, supervision. |
| **P10 · Operations** | OPS-01 → OPS-07, FIN-08, FIN-09, FIN-10, FIN-11 | Transport, maintenance, farm, utilities, procurement, stores, assets, budgets. |
| **P11 · Welfare & Compliance** | BRD-06, BRD-07, BRD-08, CMP-01 → CMP-04 | Clinic, discipline, safeguarding, ZIMSEC, MoPSE, data protection. |
| **P12 · Payroll & Intelligence** | PPL-05, PPL-06, INT-01 → INT-04 | Payroll with Zimbabwean statutory compliance, alumni, BI, public API. |
| **P13 · Commercial** | SAA-01, SAA-02, SAA-03 | Subscriptions, vendor console, onboarding machinery. Ready to sell at scale. |

**Commercial note.** P1–P5 constitute a genuinely sellable product: multi-school, sessions, full double-entry finance with both billing models, academics, and working parent and teacher apps. Get a real school onto that and let their feedback shape P6 onward. Do not build all thirteen phases before your first customer.

---

## Part 13 — What Volume 2 Will Contain

Volume 2 is written **one domain at a time, immediately before that domain is built**, and follows a fixed template so the team always knows where to look.

For every module:

1. **Scope statement** — what is in, what is explicitly out, and what is deferred
2. **Entity-relationship diagram** and full table definitions — every column, type, nullability, default, index, and foreign key
3. **Domain model** — Actions, DTOs, events, exceptions, and state machines
4. **Business rules** — numbered, individually testable, each traceable to an acceptance criterion
5. **Livewire specification** — every screen, with wireframe, component tree, properties, methods, validation, and permission requirements
6. **API specification** — every endpoint with method, path, auth, abilities, request schema, response schema, error cases, and example payloads
7. **Permission matrix** — every permission string mapped against every role
8. **Configuration schema** — every setting with type, default, scope, and validation
9. **Events published and consumed** — the integration contract with other modules
10. **Background jobs** — trigger, schedule, idempotency behaviour, and failure handling
11. **Notification triggers** — event, audience, channel, and template
12. **Reports** — every report with its columns, filters, and calculation definitions
13. **Acceptance criteria** — Gherkin scenarios covering the happy path, every error path, permission boundaries, and multi-school isolation
14. **Test plan** — unit, feature, and integration coverage requirements
15. **Migration and seed data** — what ships pre-populated for a Zimbabwean school

**Recommended order for writing Volume 2:** Domain A (Platform) → Domain D FIN-01/02/03/04/06 (Financial core) → Domain B (People) → Domain C (Academic) → then by roadmap phase.

---

## Appendix A — Sources & Regulatory References

**Curriculum & assessment**
- Ministry of Primary and Secondary Education, *Heritage-Based Curriculum Framework 2024–2030* — replaced CALA with School-Based Projects from May 2024; one project per learning area per year; two-route (academic/vocational) pathway; six core primary learning areas; O-Level five compulsory learning areas to a maximum of eight; A-Level minimum of three.
- MoPSE Circular No. 9 of 2024 — School-Based Projects implementation guidance.
- ZIMSEC Online Candidate Registration System — `https://crsz.zimsec.co.zw/`
- ZIMSEC Online Results Distribution — `https://results.zimsec.co.zw/`

**Taxation & fiscalisation**
- ZIMRA Public Notice 26 of 2024 — Virtual Fiscalisation and FDMS API
- ZIMRA Public Notice 50 of 2023 — FDMS compliance requirements
- ZIMRA Fiscalisation API documentation — obtained from the ZIMRA downloads portal (Domestic Taxes category)
- Statutory Instrument 104 of 2010 — fiscalisation of VAT-registered operators
- Income Tax Act [Chapter 23:06], Sections 80D and 80DD
- ZIMRA Fiscalisation Explained — `https://www.zimra.co.zw/domestic-taxes/corporate/fiscalisation-explained`

**Payroll & statutory**
- PAYE with separate USD and ZiG progressive bands; 3% AIDS Levy on PAYE due
- NSSA Pension and Other Benefits Scheme; NSSA Accident Prevention and Workers Compensation Scheme; insurable-earnings ceiling
- ZIMDEF Manpower Development Levy at 1% of payroll
- National Employment Council dues per applicable Collective Bargaining Agreement
- Filing calendar: PAYE (P2), NSSA, NEC, ZIMDEF and AIDS Levy due by the 10th of the following month; ITF16 annual PAYE reconciliation due 31 January
- ITF263 tax clearance — 10% withholding tax on payments to suppliers without valid clearance
- Labour Act [Chapter 28:01]

**Data protection**
- Cyber and Data Protection Act [Chapter 12:07] — POTRAZ as data protection authority

**Payment gateways (2026 landscape)**
- ContiPay — widest single-integration coverage including Visa/Mastercard with 3DS, EcoCash USD and ZWL, InnBucks, OneMoney, TeleCash, ZIPIT, ZIMSWITCH, O'Mari, Mukuru, plus disbursements
- Pesepay — strongest EcoCash push-prompt experience; provides a Dart SDK relevant to the Flutter client
- Paynow (`paynow/php-sdk`) — mature, widely recognised by parents
- ZB Smile&Pay — bank-backed alternative covering EcoCash, OneMoney, O'Mari, InnBucks and cards

**Technical stack**
- Laravel 13.x · Livewire 4 · PHP 8.4+
- `nwidart/laravel-modules` · `laravel/sanctum` · `spatie/laravel-permission` · `spatie/laravel-activitylog` · `maatwebsite/excel`

---

## Appendix B — Sign-Off

This document is the architectural baseline. Building begins once the following are agreed and recorded:

- [ ] Tenancy model confirmed — shared database with `school_id` row scoping (ADR-003)
- [ ] Hybrid delivery confirmed — Livewire admin, API for all external users (ADR-001)
- [ ] Shared-domain Action rule accepted as a CI-enforced constraint (ADR-002)
- [ ] Double-entry general ledger accepted as the financial core (ADR-006)
- [ ] Financial Integrity Charter (Part 7) accepted as binding on all finance modules
- [ ] Module catalogue and boundaries approved
- [ ] Roadmap phases and the P1–P5 minimum sellable product agreed
- [ ] Volume 2 authoring order agreed

**Next deliverable:** *Volume 2 — Detailed Functional & Technical Specification*, beginning with **Domain A · Platform & Foundation**.

---

*End of Volume 1.*
