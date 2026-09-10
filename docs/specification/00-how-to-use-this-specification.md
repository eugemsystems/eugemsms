# sERP — Complete Development Specification
## Enterprise School Management Platform · Zimbabwe Market Edition
### Master Reference Document — Volume 1 + Volume 2 (Books A–K, Complete)

| Field | Value |
|---|---|
| Document | Full specification, assembled from 14 source documents into one continuous reference |
| Coverage | **78 of 78 modules** from the original architecture blueprint — nothing outstanding |
| Total length | ~168,000 words |
| Assembled | September 2026 |
| Intended use | Primary reference context for an AI development agent (Claude) building this system from scratch |

---

## How To Use This Document

You are an AI coding agent about to build a large, multi-tenant Laravel + Livewire + Next.js + Flutter application from this specification. This section exists because a document this size is only as useful as your first ten minutes with it. Read this section fully before reading anything else.

**A note on how this file reaches you.** This document is reference material, meant to be read with a file tool when you are about to work on a specific domain — not auto-loaded into context at every session start. Claude Code's own guidance targets under 200 lines for a `CLAUDE.md`, because that file's content reloads into context every session regardless of what you're working on that day; this document is roughly two orders of magnitude past that, by design, because it is the full build specification, not a memory file. The project's root `CLAUDE.md` is short, states the handful of rules that must hold everywhere, and tells you which book under `docs/specification/` to read in full before you touch a given domain. Path-scoped rules under `.claude/rules/` load the right book's key constraints automatically when you edit files in the matching module directory. If you are reading this file directly because you were told to, that is correct — read it once, in full, before starting Part 1 of Volume 1. If you are opening it mid-task looking for one detail, use search rather than reading start to finish.

### What this document is

A complete architecture and build specification for **sERP**, a commercial, multi-tenant School Management ERP for large Zimbabwean schools — day, boarding, primary, and secondary, ECD A through Upper Six. It was produced in two layers:

- **Volume 1** (the first ~19,000 words after this briefing) is the architecture: technology decisions, the tenancy model, the session/period engine, the module catalogue, the financial integrity doctrine, and the build roadmap. Read this first, in full, before writing any code. It is the *why* behind every rule you will meet later.
- **Volume 2, Books A through K** is the detailed build specification: every module's database schema, business rules, domain actions, Livewire screens, API endpoints, permissions, settings, events, and Gherkin acceptance criteria. This is the *what and how*. It is organised as eleven books, each covering a coherent group of modules, each ending with its own build sequence and acceptance gate.

Every module from Volume 1's 78-module catalogue is specified in Volume 2. None are missing. Book K exists specifically because an internal audit caught six modules that Volume 1 promised but an earlier build pass had skipped — that correction is documented in Book K's own front matter and closing appendix, and it is worth reading as a model for how to treat this specification itself: as something to audit against its own promises, not take on faith.

### The one rule above all others

**Build in the order the books were written, and do not open a later book's module until the current book's acceptance gate is green.** Every book ends with a "Build Sequence" and an "Acceptance Gate" section. These are not suggestions. Book A's tenancy isolation suite, Book B's Financial Integrity Charter tests, and the roll-over invariant in Book A's `CORE-03` are the foundation everything after them assumes exists and works. Skipping a gate to move faster produces a system that looks finished and is not trustworthy — and trustworthiness of the financial and safeguarding data is the entire commercial premise of this product.

### The twenty rules you must never violate, regardless of what page you are on

These recur across every book. If you find yourself about to write code that contradicts one of these, stop and re-read the relevant section — you have misunderstood something, the rule has not changed.

1. **All business logic lives in Action classes.** Livewire components and API controllers are thin adapters that validate input, call exactly one Action, and format the result. Neither may contain a database write. This is enforced by a CI static-analysis rule (Book A, Part 1.1) — build that rule early and let it catch you.
2. **Every tenant-owned table carries `school_id`, non-nullable, indexed, scoped by a global Eloquent scope via the `BelongsToSchool` trait.** A query without school context is a security defect. Cross-school access requires an explicit, logged, permission-gated bypass — never a default.
3. **Every academic or financial table carries `academic_year_id` and (usually) `term_id`**, scoped by `BelongsToSession`, and writes are refused outright in `LOCKED` periods and gated in `SOFT_CLOSED` ones by `PeriodGuard`.
4. **Money is an integer in minor units with an explicit currency, never a float, never bare in an API response.** Use the `Money` value object (Book A, Part 1.4) everywhere. An amount without a currency is a defect.
5. **The General Ledger is the only source of financial truth.** No balance is ever authoritative if it is stored outside `journal_lines`. Cached balances exist for speed only and are verified against source nightly.
6. **Financial and safeguarding tables are append-only at the database grant level**, not merely by convention. `UPDATE` and `DELETE` are revoked for the application's database user on `journal_lines`, `journals` (except two permitted columns), `financial_audit_log`, `safeguarding_audit`, `missing_learner_incidents`, `collection_attempts`, and every other table marked append-only across the eleven books. Corrections are new, linked, reversing records — never edits.
7. **Every conversion between currencies stores its rate, source, and date**, and FX differences post to dedicated Realised/Unrealised FX accounts. They are never absorbed silently into a balance.
8. **A term roll-over is not complete until the reconciliation invariant holds**: `Σ(closing balances of term N) ≡ Σ(opening balances of term N+1)`, per currency, with FX movement accounted separately. If it does not hold, the roll-over is rejected and rolled back, not partially applied.
9. **Nothing is invoiced without a human approving a computed preview.** Billing runs compute, preview, and report exceptions before a single invoice or journal is committed.
10. **The subject count that drives part-time billing has exactly one source**: `learner_subject_enrolments` in `ACA-02`. There is no denormalised count column anywhere, ever — this was a deliberate, repeated design decision across multiple books.
11. **Every statutory and regulatory figure — tax bands, NSSA rates, ZIMSEC subject-count limits, filing deadlines — is versioned, effective-dated configuration, never a hard-coded constant.** Where this document flags a value `requires_confirmation`, that flag is deliberate: the underlying regulation is well-sourced but subject to periodic revision by an external body. Treat the seeded value as correct as of this document's research date and preserve the confirmation mechanism; do not silently "correct" it from your own training data, which may be older, from a different context, or simply wrong on a Zimbabwe-specific figure. Two such figures were specifically re-researched and corrected during this project (the NSSA contribution rate and the A-Level subject-count ceiling) — both corrections are documented inline where they occur, with sources, as the standard this specification holds itself to.
12. **A minor is anyone under 18.** Child-safety design in `BRD-01`, `BRD-02`, `BRD-03`, and especially `BRD-08` is not negotiable for convenience, performance, or a cleaner data model. Gender segregation in boarding allocation has no override path. The safeguarding module's vendor-access exclusion (not even the platform's own Super Admin can read a safeguarding case) is deliberate and must not be "simplified" during implementation.
13. **Tiered data visibility is structural, not client-side.** Medical, safeguarding, and compensation data are filtered by the API resource layer according to the caller's resolved access tier — the sensitive fields are *absent* from the response for an unauthorised caller, never merely hidden by the frontend.
14. **Every read of a clinical or safeguarding record is logged, not just every write.** In these domains, inappropriate *viewing* is the harm the audit trail must catch.
15. **Numbering series (invoices, receipts, admission numbers) are gapless, transactional, and row-locked.** A failed transaction voids its allocated number with a reason; it never reuses or skips one silently.
16. **Fiscalisation, gateway payments, and messaging never block the underlying operation they attach to.** A receipt posts and a parent is credited whether or not ZIMRA's FDMS, a payment gateway, or an SMS provider is reachable at that instant. Everything downstream of the core operation queues and retries.
17. **Every cross-module dependency is an explicit interface, and every book states which interfaces it opens and which it closes.** Before extending a module, check whether the data or behaviour you need already has an owning module — the temptation to rebuild a notification bus, a widget registry, or an anomaly detector that already exists elsewhere is flagged explicitly at the start of the books most likely to tempt it (see Book I §0.1 and Book J §0.1 in particular).
18. **Historical documents and reports must regenerate byte-identically from an archived period, forever.** This is why templates are versioned, why journals separate `posted_at` from `effective_at`, and why point-in-time reporting is a first-class feature, not an afterthought.
19. **Offline-first is not optional for any parent-, teacher-, or field-facing mobile workflow.** Load shedding and intermittent connectivity are named, permanent constraints of this market, not edge cases. Roll call, attendance marking, CBT delivery, and field data capture all specify offline queueing with idempotent sync.
20. **When this specification is silent or a real school's data does something no rule anticipated, extend the closest existing pattern consistently and update the specification — do not quietly diverge in code.** This document is a living reference for the life of the product, not a one-time brief.

### Technology stack, at a glance

| Layer | Choice |
|---|---|
| Backend | PHP 8.4+, Laravel 13.x, modular via `nwidart/laravel-modules` |
| Admin UI | Livewire 4 + Alpine.js + Tailwind CSS 4 (server-rendered, internal staff only) |
| API | Laravel Sanctum, REST, versioned at `/api/v1/`, consumed by Next.js and Flutter |
| Web frontend (staff/teacher/parent/student portals) | Next.js |
| Mobile | Flutter (parent, student, staff) |
| Tenancy | Shared database, `school_id` row-scoping (not database-per-tenant) |
| Database | PostgreSQL 16 or MySQL 8.4 |
| Queue/Cache | Redis + Laravel Horizon |
| Search | Laravel Scout + Meilisearch |
| Permissions | `spatie/laravel-permission`, extended with school + session scope and four reach levels (`own`/`assigned`/`section`/`school`) |
| Storage | S3-compatible, school-namespaced paths, signed URLs |
| Testing | Pest, PHPStan level 8; 80% coverage floor, 90%+ for Finance and safeguarding-adjacent code |

Full architectural rationale for every choice above — including the ADRs explaining *why* shared-database tenancy and the hybrid Livewire/API split were chosen over the alternatives — is in Volume 1, Part 2, immediately following this briefing.

### Recommended first sprint

Do not start with a module. Start with Book A, Part 1 ("Global Technical Foundations") and build, in order: the `Action` base class and its CI enforcement rule, the `BelongsToSchool` trait and its tenancy isolation test generator, the `Money` value object, the context singletons, and the middleware stack. Every one of the 78 modules that follows assumes these exist and behave exactly as specified. This is roughly Book A's own Sprint A1, and it is the correct starting point regardless of which module your commercial priorities point to next.

---

## Document Map

| # | Volume / Book | Domain(s) covered | Modules |
|---|---|---|---|
| 1 | **Volume 1** | Architecture, tenancy, session engine, configuration, module catalogue, financial doctrine, roadmap | — (overview of all 78) |
| 2 | **Book A** | Platform & Foundation | `CORE-01`–`CORE-13` |
| 3 | **Book B** | Financial Core | `FIN-01`, `FIN-02`, `FIN-03`, `FIN-04`, `FIN-05`, `FIN-06` |
| 4 | **Book C** | People & Organisation | `PPL-01`, `PPL-02`, `PPL-03`, `PPL-04` |
| 5 | **Book D** | Academic Core | `ACA-01`, `ACA-02`, `ACA-04`, `ACA-05` |
| 6 | **Book E** | Academic Depth | `ACA-03`, `ACA-06`, `ACA-07` |
| 7 | **Book F** | Boarding & Welfare | `BRD-01`–`BRD-05` |
| 8 | **Book G** | Welfare & Pastoral | `BRD-06`–`BRD-08` |
| 9 | **Book H1** | Procurement, Stores, Assets, Budgets | `FIN-08`–`FIN-11` |
| 10 | **Book H2** | Operations & Estates | `OPS-01`–`OPS-07` |
| 11 | **Book H3** | Payroll, Fiscalisation & Compliance | `PPL-05`, `FIN-12`–`FIN-14`, `CMP-01`–`CMP-04` |
| 12 | **Book I** | Communication & Portals | `COM-01`–`COM-08` |
| 13 | **Book J** | Intelligence & SaaS Control | `INT-01`–`INT-04`, `SAA-01`–`SAA-03` |
| 14 | **Book K** | Closing the Catalogue | `FIN-07`, `PPL-06`, `ACA-08`–`ACA-11` |

## Complete Module Index (78 of 78)

| Code | Module | Book | Code | Module | Book |
|---|---|---|---|---|---|
| CORE-01 | System Installer & Provisioning | A | FIN-06 | Multi-Currency & FX Engine | B |
| CORE-02 | Tenancy & School Registry | A | FIN-07 | Scholarships, Bursaries & Discounts | K |
| CORE-03 | Academic Session & Period Engine | A | FIN-08 | Procurement & Accounts Payable | H1 |
| CORE-04 | Settings & Extensibility | A | FIN-09 | Inventory, Stores & Requisitions | H1 |
| CORE-05 | Identity, Auth & RBAC | A | FIN-10 | Fixed Assets & Depreciation | H1 |
| CORE-06 | Numbering, Templates & Documents | A | FIN-11 | Budgeting & Commitment Accounting | H1 |
| CORE-07 | Workflow & Approvals Engine | A | FIN-12 | Financial Reporting & Period Close | H3 |
| CORE-08 | Audit, Activity & Data Integrity | A | FIN-13 | ZIMRA Fiscalisation (FDMS) | H3 |
| CORE-09 | Notification Orchestration Bus | A | FIN-14 | Student Wallet & Tuckshop | H3 |
| CORE-10 | File Vault & Media Management | A | BRD-01 | Hostel, Room & Bed Allocation | F |
| CORE-11 | Data Import & Migration Toolkit | A | BRD-02 | Roll Call & Movement | F |
| CORE-12 | Jobs, Scheduling & Observability | A | BRD-03 | Exeat, Leave & Visitor Mgmt | F |
| CORE-13 | Backup, Restore & DR | A | BRD-04 | Catering, Menus & Kitchen | F |
| PPL-01 | Student Information System | C | BRD-05 | Laundry & Linen | F |
| PPL-02 | Admissions & Enrolment CRM | C | BRD-06 | Health, Clinic & Sanatorium | G |
| PPL-03 | Guardian, Family & Fee Liability | C | BRD-07 | Discipline, Conduct & Behaviour | G |
| PPL-04 | Staff & Human Resources | C | BRD-08 | Counselling & Safeguarding | G |
| PPL-05 | Payroll & Statutory Deductions | H3 | OPS-01 | Transport & Fleet Management | H2 |
| PPL-06 | Alumni & Institutional Development | K | OPS-02 | Maintenance & Works Management | H2 |
| ACA-01 | Curriculum, Learning Areas & Pathways | D | OPS-03 | Estates, Farm & Production Units | H2 |
| ACA-02 | Class, Stream & Subject Enrolment | D | OPS-04 | Utilities & Energy Management | H2 |
| ACA-03 | Timetable & Scheduling Engine | E | OPS-05 | Facilities Booking & External Hire | H2 |
| ACA-04 | Attendance | D | OPS-06 | Security, Gate & Access Control | H2 |
| ACA-05 | Assessment, Grading & Report Cards | D | OPS-07 | Sport, Houses & Co-curricular | H2 |
| ACA-06 | School-Based Projects & Legacy CALA | E | COM-01 | Messaging Gateways & Delivery | I |
| ACA-07 | Examinations Administration | E | COM-02 | Event-Driven Automation Rules | I |
| ACA-08 | Online Assignments & LMS | K | COM-03/04/05 | Parent/Learner/Staff Portal Services | I |
| ACA-09 | Computer-Based Testing | K | COM-06 | Calendar, Events & Notice Board | I |
| ACA-10 | Library & Textbook Management | K | COM-07 | Virtual Meetings & Online Classes | I |
| ACA-11 | Teaching Quality & Supervision | K | COM-08 | Feedback, Surveys & Complaints | I |
| FIN-01 | Chart of Accounts & General Ledger | B | CMP-01 | ZIMSEC Candidate Registration | H3 |
| FIN-02 | Fee Structure & Billing Engine | B | CMP-02 | MoPSE Returns & EMIS Reporting | H3 |
| FIN-03 | Invoicing, Statements & Debtors | B | CMP-03 | Data Protection, Consent & Privacy | H3 |
| FIN-04 | Receipting, Cashiering & Till Control | B | CMP-04 | Policy & Document Register | H3 |
| FIN-05 | Payment Gateways & Reconciliation | B | INT-01 | Reporting Engine & Data Warehouse | J |
| | | | INT-02 | Executive Dashboards | J |
| | | | INT-03 | Early Warning & Predictive Analytics | J |
| | | | INT-04 | Public API, Webhooks & Integrations | J |
| | | | SAA-01 | Licensing, Subscription & Entitlement | J |
| | | | SAA-02 | Vendor Control Centre | J |
| | | | SAA-03 | Onboarding, Support & Customer Success | J |

---

## What Follows

Everything below this line is the complete, unedited specification: Volume 1 in full, then Books A through K in full, each separated by a clear divider. Each book retains its own front matter, prerequisites, part-by-part detail, build sequence, and acceptance gate exactly as written — those internal cross-references (e.g. "see Book A §1.2") remain accurate now that everything lives in one document.

Begin.

