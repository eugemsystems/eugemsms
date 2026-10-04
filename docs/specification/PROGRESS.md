# Build Progress

This file is the single source of truth for **what has actually been built**,
checked against the real codebase, not memory or assumption. Read this
before starting any new work, and update it (one line, same turn) the
moment a module's backend or admin UI ships and is tested/committed.

Two separate tracks are tracked per module, because they are built in two
separate passes in this project's history:

- **Backend** — migrations, models, factories, domain Actions, registries,
  Pest tests. Tracked per Book, in the original build order (Book A → K).
- **Admin UI** — Livewire screens, Blade views, routes, sidebar nav,
  permissions, admin-UI Pest tests, on top of an already-complete backend.
  Tracked per module, **in the same book order**, as its own later pass.

**How to verify a claim in this file, don't just trust it**: for backend,
check `Modules/{Name}/Domain/Actions/` and `Modules/{Name}/tests/` exist and
`php artisan test --compact` passes. For admin UI, check
`Modules/{Name}/Livewire/` for that module's own subdirectory and
`Modules/{Name}/routes/` for a registered route file — an empty or missing
`Livewire/` directory means no UI exists yet, regardless of what any commit
message might imply.

---

## Backend — 100% complete (all 78 of 78 modules)

Every module across Books A through K has its full domain layer: migrations,
models, factories, Actions, registries, and a Pest test suite. This was
built first, straight through, before any admin UI work started.

| Book | Modules | Status |
|---|---|---|
| A — Platform & Foundation | CORE-01–CORE-13 | ✅ |
| B — Financial Core | FIN-01–FIN-06 | ✅ |
| C — People & Organisation | PPL-01–PPL-04 | ✅ |
| D — Academic Core | ACA-01, ACA-02, ACA-04, ACA-05 | ✅ |
| E — Academic Depth | ACA-03, ACA-06, ACA-07 | ✅ |
| F — Boarding & Welfare | BRD-01–BRD-05 | ✅ |
| G — Welfare & Pastoral | BRD-06–BRD-08 | ✅ |
| H1 — Procurement, Stores, Assets, Budgets | FIN-08–FIN-11 | ✅ |
| H2 — Operations & Estates | OPS-01–OPS-07 | ✅ |
| H3 — Payroll, Fiscalisation & Compliance | PPL-05, FIN-12–FIN-14, CMP-01–CMP-04 | ✅ |
| I — Communication & Portals | COM-01–COM-08 | ✅ |
| J — Intelligence & SaaS Control | INT-01–INT-04, SAA-01–SAA-03 | ✅ |
| K — Closing the Catalogue | FIN-07, PPL-06, ACA-08–ACA-11 | ✅ |

Last backend module to ship: **PPL-06 (Alumni & Institutional Development)**,
commit `e4b605b` — the final module of the original 78-module spec.

Known, deliberately-documented backend gaps (each called out in the relevant
Action/model's own docblock, not silently missing — see each module's own
`.ai/rules/*.md` entry for detail):
- No HTTP controllers or public API routes exist for any module's own
  domain layer independent of the admin UI below — the admin UI pass is
  building the first UI/routing layer most modules have ever had.
- FIN-08 (procurement) exists, but a few spec-named cross-module FKs into
  it from later modules were deliberately omitted rather than fabricated
  (e.g. ACA-10's `purchase_order_id`) — grep the owning module's own
  docblocks for "documented gap" before assuming an integration is live.
- FIN-05's gateway drivers: only a `fake` test/sandbox driver is registered;
  ContiPay/Pesepay/Paynow/SmilePay need real sandbox credentials this build
  doesn't have and are not implemented.
- A handful of scheduled jobs are "real logic, deferred cron wiring" —
  the Action a job would call exists and is tested; the actual
  `routes/console.php` schedule entry does not. Each such case says so in
  its own Action docblock (e.g. `PollPendingIntentAction`, FIN-05).

---

## Admin UI — in progress, Book A and Book B complete

This pass retrofits Livewire screens onto the already-complete backend,
**in the same book order as the original build** (A → B → C → D → E → F →
G → H1 → H2 → H3 → I → J → K), one module at a time: read that module's
own spec section → build its screens on the existing Actions (adding a
small new Action only when the UI genuinely needs one the domain layer
never had reason to build, e.g. "edit" when only "create" existed) → write
admin-UI Pest tests → Pint → PHPStan (module, then whole-app) → full suite
→ commit → push.

### Book A — Platform & Foundation — ✅ complete

| Module | Screens | Status |
|---|---|---|
| CORE-01 | System Installer & Provisioning | ✅ (`Livewire/Install/`) |
| CORE-02 | Tenancy & School Registry | ✅ (`Livewire/Schools/`) |
| CORE-03 | Academic Session & Period Engine | ✅ (`Livewire/Structure/`, `Sessions/`) — commit `c391dd4` |
| CORE-04 | Settings & Extensibility | ✅ (`Livewire/Settings/`, `CustomFields/`, `FeatureFlags/`) — commit `bb6170e` |
| CORE-05 | Identity, Auth & RBAC | ✅ (`Livewire/Users/`, `Roles/`, `Permissions/`, `Profile/`, `Auth/`) — commits `8a03cce`, `580feed`, `b7a0184`, `972cc1e` |
| CORE-06 | Numbering, Templates & Documents | ✅ (`Livewire/Numbering/`, `Templates/`, `Documents/`) — commits `1e1b850`, `d4764b4`, `a787ff4` |
| CORE-07 | Workflow & Approvals Engine | ✅ (`Livewire/Approvals/`) — commit `396533b` |
| CORE-08 | Audit, Activity & Data Integrity | ✅ (`Livewire/Audit/`) — commit `fe37398` |
| CORE-09 | Notification Orchestration Bus | ✅ (`Livewire/Notifications/`) — commit `c0461b0` |
| CORE-10 | File Vault & Media Management | ✅ (`Livewire/Files/`) — commit `382f2a9` |
| CORE-11 | Data Import & Migration Toolkit | ✅ (`Livewire/Imports/`) — commit `ae47797` |
| CORE-12 | Jobs, Scheduling & Observability | ✅ (`Livewire/Scheduling/`) — commit `de6e637` |
| CORE-13 | Backup, Restore & Disaster Recovery | ✅ (`Livewire/Backups/`) — commit `acefbcc` |

Plus significant cross-cutting UI fixes/features layered in during this
book's pass: 2FA/passkey enrolment, impersonation wiring, ulid-based
routing everywhere, a shared advanced data-table component
(`resources/views/components/data-table.blade.php`, commit `73845d2`,
driven by `Modules\Core\Livewire\Concerns\InteractsWithDataTable` — **every
later module's own list screen should use this, not a hand-rolled table**).

### Book B — Financial Core — ✅ complete

| Module | Screens | Status |
|---|---|---|
| FIN-01 | Chart of Accounts & General Ledger | ✅ — commit `ffb8ff0` |
| FIN-02 | Fee Structure & Billing Engine | ✅ — commit `f030d4c` |
| FIN-03 | Invoicing, Statements & Debtor Management | ✅ — commit `33b42f9` |
| FIN-04 | Receipting, Cashiering & Till Control | ✅ — commit `07df854` |
| FIN-05 | Payment Gateways & Reconciliation | ✅ — commit `3d6dcb3` |
| FIN-06 | Multi-Currency & FX Engine | ✅ — commit `e7ecf76` |

Plus a bursar-facing field-by-field guide at `docs/finance-admin-guide.md`
(extended with every module as it ships) and the Finance sidebar grouped by
module (commit `1c4f5b2`).

### Book C — People & Organisation — 🟡 in progress

Backend build order was `PPL-01 → PPL-03 → PPL-02 → PPL-04` (Book C §0.2 —
"without `PPL-03`, invoices have nobody to bill"); the admin-UI pass
follows the same order.

| Module | Screens | Status |
|---|---|---|
| PPL-01 | Student Information System | 🟡 partial (see note) |
| PPL-03 | Guardian, Family & Fee Liability | ⬜ ← next up |
| PPL-02 | Admissions & Enrolment CRM | ⬜ |
| PPL-04 | Staff & Human Resources | ⬜ |

**PPL-01 note.** Built: Directory, Profile (Overview/Academic/Financial/
Guardians tabs), Create (with duplicate check), Edit, **Change billing
attribute** (with a real live fee-impact preview, reusing FIN-02's own
`FeeStructureResolver`/`FeeLineCalculator`), Change status (suspend/
withdraw/readmit/graduate), and an on-demand Duplicate scanner. Also: a
new `PreviewBillingAttributeChangeAction`, a real pre-existing bug fixed
in `DetectPossibleDuplicatesAction` (date-of-birth matching was silently
broken on every school), and two documented Livewire gotchas (see
`.ai/rules/people.md`). **Deliberately not built**, because the backend
for them doesn't exist at all (verified: no migration) — Documents, Prior
schooling, Siblings, a real Timeline tab, Class/House allocation, Bulk
operations, ID cards, Merge duplicates. See `.ai/rules/people.md`'s own
"PPL-01's admin UI pass found four tables..." note before touching any of
these — they need new backend work, not a UI retrofit.

### Book D — Academic Core — ⬜ not started
ACA-01, ACA-02, ACA-04, ACA-05. `Modules/Academic/Livewire/` does not exist yet.

### Book E — Academic Depth — ⬜ not started
ACA-03, ACA-06, ACA-07.

### Book F — Boarding & Welfare — ⬜ not started
BRD-01–BRD-05. `Modules/Boarding/Livewire/` does not exist yet.

### Book G — Welfare & Pastoral — ⬜ not started
BRD-06–BRD-08 (safeguarding — remember the inverted-access rule from
`CLAUDE.md` applies to this UI too). `Modules/Welfare/Livewire/` does not exist yet.

### Book H1 — Procurement, Stores, Assets, Budgets — ⬜ not started
FIN-08–FIN-11 all live in **`Modules/Stores`** (verified: every FIN-08–11
migration's own docblock attributes to it), not `Modules/Finance`.
`Modules/Stores/Livewire/` does not exist yet.

### Book H2 — Operations & Estates — ⬜ not started
Verified module ownership (one Laravel module each): OPS-01 → `Transport`,
OPS-02 → `Operations`, OPS-03 → `Farm`, OPS-04 → `Utilities`, OPS-05 →
`Facilities`, OPS-06 → `Security`, OPS-07 → `Sport`. None have a `Livewire/`
directory yet.

### Book H3 — Payroll, Fiscalisation & Compliance — ⬜ not started
Verified module ownership: PPL-05 → `Payroll`, FIN-12 → `Reporting`, FIN-13
→ `Fiscal`, FIN-14 → `Wallet`, CMP-01–CMP-04 → `Compliance`. None have a
`Livewire/` directory yet.

### Book I — Communication & Portals — ⬜ not started
COM-01–COM-08. `Modules/Comms/Livewire/` does not exist yet.

### Book J — Intelligence & SaaS Control — ⬜ not started
INT-01–INT-04 (`Modules/Intelligence`), SAA-01–SAA-03 (`Modules/Saas`) —
neither has a `Livewire/` directory yet.

### Book K — Closing the Catalogue — ⬜ not started
FIN-07 (lives in `Modules/Finance`, alongside FIN-01–06), PPL-06 (lives in
`Modules/People`, alongside PPL-01–04), ACA-08–ACA-11 (lives in
`Modules/Academic`, alongside ACA-01–07). None have UI yet.

---

## How to keep this file honest

- After a module's admin UI ships (tested, Pint/PHPStan clean, committed),
  flip its row from ⬜ to ✅ and add the commit hash, in the same turn as the
  commit — not as a separate follow-up.
- If a module's UI is *partial* (some screens built, some deliberately
  deferred), say so explicitly next to it rather than marking the whole
  module ✅ — mirror how `.ai/rules/finance.md`'s own "deliberately NOT
  built" notes work.
- Before trusting an entry here that looks stale, verify it the same way
  this file was built: check the actual `Livewire/` directory, not just
  this table.
