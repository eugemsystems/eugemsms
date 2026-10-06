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

## Admin UI — in progress: Books A, B, I, K complete; C–H3 and J partial (deliberately deferred items remain)

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

## Gap-closing pass (after Book K)

A full re-check of this file against the code found that the **only** specification tables
missing from the schema were Book C's sixteen; everywhere else the schema existed and the gaps
were missing Actions, screens or wiring. This pass closes them, in order of how much a school
depends on them. Status per item below; anything not listed as done is still open.

**Done**
- **Scheduled jobs (all books).** Roughly 45 per-school jobs and three platform jobs had a working
  Action but no cron entry. They now run through `serp:run-task {key}` (`Core`
  `ScheduledTaskHandlerRegistry` + `RunScheduledTaskCommand`; failures are isolated per school;
  `ResolveSystemActorAction` gives a job a `users.id`). `ModuleScheduledTasksTest` runs every
  registered job against an empty school. See `.ai/rules/providers.md`.
- **ACA-05 report cards.** Assessment weights must total 100% before results compute
  (AC-ACA-05-001); approve; class/head comments; generate with the fee gate (withheld cards are
  stored, AC-004); publish and release without regenerating (AC-005); regenerate a new version
  when a published mark is amended (AC-003); transcripts; analytics. Screens: `Results\Review`,
  `ReportCards\{Run,Withheld,Publish}`, `Results\{Transcripts,Analytics}`. Also fixed:
  recomputing results no longer resets a reviewed/published status; document templates now
  HTML-escape values; the report-gate override needs a reason. Still open: CORE-07 approval
  workflow for amending a published mark; per-class moderation sign-off beyond approval.
- **Book C tables and features** (all sixteen tables built): staff qualifications; learner
  documents (with expiry alerts), prior schooling, sibling links (now counted by the sibling
  discount), timeline, ID cards, transfer-out with a clearance check (`LearnerClearanceRegistry`:
  library, boarding property and fees register their own checks); households, sponsorships
  (beneficiaries bill the sponsor through a real fee liability; budget envelope and beneficiary
  limit enforced), guardian verification, the contact-update approval queue, E.164 phone
  normalisation; the enquiry pipeline, application documents, entrance exams (seating, marks,
  ranking), interviews and the admissions funnel. 16 new screens. Still open in Book C: merging duplicate learners (needs a design that respects
  append-only financial records; guardian merge is built),
  the
  structured appraisal rubric, the public application form and `/api/v1` endpoints.

- **Finance documents (CORE-06).** Invoices and receipts render to a stored, verifiable document when
  issued (listeners never block the invoice or receipt); statements render on demand from journal
  lines. Templates are registered versioned defaults. "Print / download" on the invoice screen and
  the statement screen; downloads are recorded. Still open: a receipt download button.
- **FIN-10 capitalisation.** Capital PO lines and capitalisable stock items name an asset category
  and now capitalise automatically on receipt/issue (see FIN-10 note).
- **COM-08 respondent form and complaint rating.** `Surveys\Respond` (any school member answers an
  open survey aimed at them once; anonymous surveys never record who; skip logic hidden in the form
  and dropped again by the Action). `SubmitSurveyResponseAction` now refuses closed/expired surveys,
  missing required answers, answers outside the options, out-of-range scale/NPS values and a second
  answer from the same identified respondent. The raiser can rate a resolved complaint 1–5, once
  (`RateComplaintResolutionAction`). Anonymous surveys cannot be de-duplicated by design.
- **COM-06 newsletter sending.** `SendNewsletterAction` emails an issue through the notification bus
  (whole-school: active guardians and staff; staff: staff only; one message per distinct address;
  section/level audiences are refused because the issue does not record a target). "Send now" on
  `Newsletters\Compose`, and a `comms.send_due_newsletters` job sends scheduled issues.
- **FIN-08 contracts.** Record, renew and terminate supplier contracts; blacklisted suppliers and
  duplicate numbers refused.
- **OPS-02 milestones and contractor.** Capital projects get milestones (payment percentages capped
  at 100%, completed only while in progress) and a named main contractor.
- **BRD-06 consultations.** Recorded and listed on the Tier 3 clinical record.
- **`/api/v1` foundation and guardian slice (Volume 1 §9).** `routes/api.php` (prefix `api/v1`, Sanctum,
  `serp.api` stack): `auth/otp/request`, `auth/otp/verify`, `auth/login` (a 2FA account is sent to the
  OTP flow), `auth/refresh` (single-use rotation), `auth/logout`; `me`, `me/schools`, `me/session`;
  `guardians/me/children`; `finance/balances`, `finance/invoices[/{id}]`; `students/{id}/report-cards`;
  `communications/notices`. Success/data/meta envelope, coded errors for validation, auth, throttling and
  not-found, money only as `{amount_minor, currency, formatted}`, 25/100 paging, per-route token abilities,
  and `serp.idempotent` (Idempotency-Key required, replay, conflict) ready for payment endpoints.
  Parent endpoints only ever see their own linked, currently effective learners; balances are absent
  without `may_view_full_balance`; a withheld report card carries no marks and no reason.
  **Security fix found on the way:** a revoked Sanctum token (logout, refresh rotation, device removal)
  kept authenticating until expiry; `CoreServiceProvider` now rejects it. **Also built**: teacher register endpoints
  (`teacher/classes`, `teacher/classes/{id}/attendance` GET/POST — offline-safe per-record idempotency keys,
  conflicts reported not overwritten) and a learner reading their own report cards, attendance and
  timetable (never a fee balance). **Also built since**: teacher marks entry, guardian exeat, student attendance/timetable (see `routes/api.php`). **Mobile/parent API gap pass (written, NOT run, NOT verified: no dependencies install in the sandbox, only `php -l` was run on each file).**
  (1) `X-Academic-Year-Id`/`X-Term-Id` were already resolved and validated in `SetSessionContext` (attendance, timetable, exeats, marks use them); report cards now narrow to the named term/year only when a header is sent (history otherwise).
  (2) `serp.api-locale` (`SetApiLocale`, first in the `serp.api` group) negotiates `Accept-Language` to en/sn/nd, falls back to en, sets `Content-Language`; no sn/nd translation files exist, so strings stay English.
  (3) `GET students/{student}/documents` and `.../{document}/download` (ability `documents.read`): CORE-06 documents issued against the learner plus their published report cards; withheld cards, expired documents, other learners' and other schools' are 404. Assumptions: the file is streamed through the authenticated endpoint rather than the spec's signed redirect; `StudentDocument` (identity/permit file records) and invoice documents are not exposed (invoices keep their finance endpoints).
  (4) `GET students/{student}/lms/courses` and `.../homework` (ability `homework.read`): read-only; marks/feedback shown only once marked. Assumptions: learner-scoped under `students/{student}` (not the spec's `lms/my-courses`) so guardian-to-learner authorisation is the same as every other parent endpoint; content files are listed with metadata/external URL but have no download endpoint; submit/mark/teacher LMS endpoints remain unbuilt.
  (5) API keys/webhooks: skipped, INT-04 covers the base and no new gap was found. `docs/api/openapi.yaml` was not extended with the new routes. **Still not built:** LMS submit/mark/teacher endpoints, LMS content file download, translations. Cross-guardian and cross-school denial tests are in `GuardianPortalApiTest.php`.
- **SAA vendor impersonation (BR-SAA-02-002).** Consent-gated by a structured grant, not a typed
  reference: the customer's own administrator (`core.support_access.manage`, screen
  `Core\Users\SupportAccess`) grants access for one named ticket for 1–72 hours and can withdraw it
  at any time, which ends open sessions at once. Vendor staff open a session from `Saas\Vendor\Tenants\Show`
  (`StartVendorImpersonationAction`): vendor operators only, never a vendor target, only within the
  grant's tenant, only for the ticket the grant names. The session is read-only — the base `Action`
  refuses every write while one is open, so no module can forget — ends at the sooner of the platform
  maximum and the grant, is stamped with the grant, and appears on the customer's screen (who, when,
  how long). The start is recorded in the vendor console audit. Same-browser session swap with the
  existing banner to return, as CORE-05 already does; a separate-subdomain session would need
  per-tenant hosts. The customer's granting administrator is notified when a session opens (9963966, `core.support_session_opened`).
- **FIN-05 Pesepay gateway, parent payments API.** `PesepayGatewayDriver` (hosted checkout, EcoCash push,
  polling, result callback authenticated by the integration-key header and confirmed with check-payment), public
  `POST /api/v1/webhooks/payments/{driver}`, and `finance/payment-methods`, `POST finance/payments`
  (Idempotency-Key required), `GET finance/payments/{id}`. Keys are read from `PESEPAY_INTEGRATION_KEY` /
  `PESEPAY_ENCRYPTION_KEY` (never committed) or a school's own gateway row. **Sandbox verified** (57ca9bd, cd2f79c; see the Pesepay sandbox check below), **live production still unverified**: the
  ZWG code (`PZW201`, overridable) and a real result callback are not exercised against Pesepay.
- **ACA-04 attendance reports.** Class report, learner heatmap, absence follow-up and register CSV.
- **Bulk class and house allocation.** `Allocation\Bulk` places up to 300 learners in a class and/or a
  house in one step (`BulkAllocateClassAction`, `AllocateStudentsToHouseAction`); wrong grade level, already
  there, not currently enrolled, and a full class are skipped and listed with the reason. House moves are
  written to the learner timeline. Other Book C bulk operations (status changes, exports) remain open.
- **Parent app access (Book C PPL-03).** `Guardians\PortalAccess` (`people.guardians.portal_access`) gives a guardian
  an account for their phone number (or links the existing account for it), attached to the school with no
  password — they sign in with a one-time code, which is what the mobile API's OTP flow expects. Refused with
  the reason when there is no phone, no learner currently at the school, or the number belongs to another
  guardian or to vendor staff. Withdrawing unlinks the guardian, deactivates their membership of the school
  and signs every device out. No invitation message is sent yet.
- **FIN-12 management reports.** `Financial\Management`: departmental (income, expense, net per cost centre,
  with un-centred lines on their own row so it adds to the income statement) and fee collection (billed,
  collected, outstanding and rate by grade level, voided invoices excluded).
- **BRD-04 catering costs.** Real stores-backed costing and availability; `Catering\Costs` per meal, per week, over-production.
- **Pesepay sandbox check.** Against `https://api.test.sandbox.pesepay.com/payments-engine` the supplied key works: EcoCash
  make-payment (PZW211, USD) answers 200 with an encrypted body, `0777777777` -> SUCCESS and `0770000000` -> FAILED, and
  check-payment returns the status. Finding fixed: make-payment returns `referenceNumber: null` with the reference only in
  `pollUrl`; the driver now reads it from there. Sandbox is USD-only (EcoCash, Visa, Mastercard); not yet exercised: a
  redirect checkout, the result callback to a public URL, and the Visa/CABS cards.
- **Pesepay docs pass.** Read the developer docs end to end and corrected: the result callback is plain JSON with the
  integration key in `Authorization` (not encrypted) and is confirmed with check-payment before settling (an unconfirmable
  callback is recorded and the scheduled poll settles it); the callback's key header is never stored; only `SUCCESS` is
  paid and every documented terminal status maps to failed/cancelled; ZWG is sent as `ZiG`. Live sandbox, USD: redirect
  initiate (redirectUrl + referenceNumber), EcoCash make-payment success/failure and check-payment all confirmed. Not
  exercised live: a real result callback (needs a public HTTPS URL) and card entry on the hosted page (browser-only).
- **Vendor-session notice and parent invitation.** Opening a vendor support session now notifies the administrator who
  granted access (in-app, plus email; `core.support_session_opened`). Giving a guardian parent-app access now sends them
  an SMS (and email where on file) invitation (`people.parent_app_invitation`); a failed send never blocks either.
- **Parent exeat API and exeat blocks.** `GET /exeat-types`, `GET|POST /students/{student}/exeats` (idempotent) for
  guardians who may authorise exeats. Also fixed a gap that affected the staff screen too: the suspension and fee-arrears
  blocks were never actually computed (both flags were always false); `ExeatEligibility` now computes them from learner status
  and the school's `boarding.exeat_block_on_fee_arrears` / threshold settings and both callers use it.
- **Teacher marks API.** `GET /teacher/assessments[/{id}]`, `POST .../marks` (per-learner results, offline-safe overwrite) and
  `POST .../submit`, scoped by `academic.result.enter` reach.
- **PPL-03 duplicate guardian merge.** `Guardians\Duplicates` lists guardians sharing a phone or name; `MergeGuardiansAction`
  (`guardians.merge`) folds one into another: learner links move (rights OR-ed, a court restriction on either wins, the
  duplicate's overlapping link goes inactive), 18 other tables are re-pointed, the app account moves across (refused when both
  have their own), and the duplicate is kept as `merged`. Learner merge (`ACT-MergeDuplicateStudents`) is deliberately still
  not built: the spec asks it to reassign every financial record, which the append-only ledger rule forbids, so that needs a
  design decision first (see the note under Book C).
- **ACA-03 drag-and-drop editor.** Class timetable grid with draggable lessons, clash-refused moves with the conflict named,
  undo of the last move, remove; double lessons move as a pair only by removing and re-placing (not yet supported).
- **FIN-12 statements.** `Financial\BalanceSheet` (assets, liabilities, equity and current earnings from
  the journal, as at any date, with a balance check) and `Financial\CashFlow` (direct method: bank
  movements by journal type between computed opening and closing positions).
- **FIN-11 forecasts.** Fee-income and cash-flow projections computed from actuals.
- **PPL-02 public enquiry form.** `/apply/{slug}` (unauthenticated, throttled 10/min, honeypot and minimum fill time) creates
  a `website` enquiry for an open intake through `CreateEnquiryAction`; the school comes from the intake's globally unique
  `public_form_slug`, never from the request. Staff switch it on/off per intake (`SetIntakePublicFormAction`) on the Intakes
  screen. Deliberately enquiry-only: a full application (documents, fee, verified guardian) is never created anonymously.
- **FIN-13 audit log.** Every FDMS request/response is written to `fiscal_audit_log` by a driver
  decorator; a failed log write never blocks the fiscal call.

**Still open (not yet started in this pass)**:
ACA-04 period-mode marking; FIN-12 board pack (the prior-period view is the
Income statement's reconciling items); COM gaps (survey distribution, the head's termly complaint report); the rest of the `/api/v1` surface — LMS submit/mark/teacher endpoints and sn/nd translations (see the mobile/parent API gap pass above); payment gateways other than Pesepay.

---

### Book C — People & Organisation — 🟡 in progress

Backend build order was `PPL-01 → PPL-03 → PPL-02 → PPL-04` (Book C §0.2 —
"without `PPL-03`, invoices have nobody to bill"); the admin-UI pass
follows the same order.

| Module | Screens | Status |
|---|---|---|
| PPL-01 | Student Information System | 🟡 partial (see note) |
| PPL-03 | Guardian, Family & Fee Liability | 🟡 partial (see note) |
| PPL-02 | Admissions & Enrolment CRM | 🟡 partial (see note) |
| PPL-04 | Staff & Human Resources | 🟡 partial (see note) |

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

**PPL-03 note.** Built: Guardian directory, profile (linked learners +
active fee-liability rules, both read-only with links out), Create
(individual or organisation guardian — create-only, no update action
exists), and the student-scoped relationship editor
(`People\Students\Guardians`, replacing `People\Students\Show`'s old
"Guardians" tab placeholder) backed by `LinkGuardianToStudentAction`/
`DeactivateStudentGuardianAction`, including the last-fee-responsible-
guardian refusal (BR-PPL-03-004/022). **Already existed, discovered
during this pass**: the spec's own "Liability designer" screen was
built during the FIN-03 pass as `Finance\Liabilities\Editor`
(`finance.liabilities.editor`) — PPL-03's UI links out to it rather
than duplicating it. **Deliberately not built**, because the backend
for them doesn't exist at all (verified: no migration) — Households,
Sponsorships (index + detail), Portal access, Contact update queue,
Duplicate review, Verification. See `.ai/rules/people.md` before
touching any of these.

**PPL-02 note.** Built: Intakes (list+create), Applications (list —
also stands in for the spec's separate Waitlist screen, Create — the
staff-facing stand-in for the unbuilt public form, Show — one
lifecycle screen hosting the whole fee/offer/decline/accept/deposit/
expire action bar), and Convert (preflight, guardian-match preview,
deposit-credit preview, one confirm). **Deliberately not built**,
because the backend for them doesn't exist at all (verified: no
migration) — Enquiries (the CRM kanban pipeline), Entrance exams,
Interviews, Funnel analytics. See `.ai/rules/people.md` before
touching any of these.

**PPL-04 note.** Built: `Staff\{Index,Show,Create,Contracts,
Disciplinary,Compliance}`, `Staff\ExitProcessing` (not `Exit` —
`exit` is a reserved PHP keyword and cannot name a class),
`Establishment\Index`, `Allocation\TeacherMatrix`, `Leave\{Request,
Approvals,Balances}`, `Duty\Rosters`, `Appraisal\{Index,Show}` — 27
backend Actions' worth of screens, by far the largest module this
pass has covered. Compensation fields (salary/banking) are absent
from `Staff\Show`'s response entirely for a viewer without
`people.staff.view_compensation` (AC-PPL-04-009); disciplinary case
detail is only ever read through `ViewDisciplinaryCaseAction`, never
a direct query (BR-PPL-04-020). **Deliberately not built**, because
the backend for it doesn't exist at all (verified: no migration) —
a Qualifications tab (`staff_qualifications`). Three screens
deliberately simplified from the spec's own description (self-
service folded into HR-facing capture, a pick-and-submit allocation
form instead of a drag-and-drop grid, free-text appraisal notes
instead of a structured rubric) — see `.ai/rules/people.md` for why
each one was a reasonable cut, not a missed requirement. Book C's
four modules (PPL-01/02/03/04) are now all built and each carries its
own documented partial-coverage note — Book C stays 🟡 rather than
✅ because every module genuinely has a real, named gap, not because
anything is unfinished-by-oversight.

### Book D — Academic Core — 🟡 in progress

| Module | Screens | Status |
|---|---|---|
| ACA-01 | Curriculum, Learning Areas & Pathways | 🟡 partial (see note) |
| ACA-02 | Class, Stream & Subject Enrolment ⭐ | 🟡 partial (see note) |
| ACA-04 | Attendance | 🟡 partial (see note) |
| ACA-05 | Assessment, Grading & Report Cards | 🟡 partial (see note) |

**ACA-01 note.** Built: `Curriculum\{Frameworks,Subjects,Groups,Offerings,
Pathways,SelectionRules,Prerequisites,Syllabi}` — all list+create.
`SelectionRules` carries a live rule tester running the real
`SubjectSelectionRuleEngine`; `Frameworks` carries the
`requires_confirmation` banner (read live each render — no "marked
reviewed" persistence column exists). **Four new, small, create-only
Actions** (`CreateCurriculumFrameworkAction`/`CreateSubjectGroupAction`/
`CreateSubjectAction`/`CreateSubjectSelectionRuleAction`) — the domain
layer had models and factories for all four but no Action anywhere had
ever created one (verified: every prior row came from a test/demo
factory call). See `.ai/rules/academic.md`.

**ACA-02 note ⭐.** Built: `Allocation\Classes`, `Enrolment\LearnerSubjects`
⭐ (add/drop with effective date, dated history, live fee-impact preview
through Finance's `PreviewIndicativeFeeAction`), `Groups\Index` (also
stands in for the spec's "Subject registers"), `Groups\Allocate`,
`Selection\Form` (staff-facing stand-in for the unbuilt public/portal
form), `Selection\Approvals` (one lifecycle screen, guardian-approve/
school-approve/reject), `Enrolment\BillingCheck` ⭐ (the part-time
billing reconciliation screen). **Deliberately not built**: Bulk subject
enrolment (no bulk domain Action exists). See `.ai/rules/academic.md`.

**ACA-04 note.** Built: `Attendance\{Mark,Daily,Compliance,Chronic,
ReasonCodes}`. `Mark` is `daily` mode only (period/subject modes need
an `ACA-03` timetable slot this screen doesn't surface); amendment is
folded into `Mark` itself rather than a separate route.
Gap-closing pass: `Attendance\Reports` — class report for a range, learner heatmap (unmarked days
blank), absence follow-up (recent absences and whether the parent was told) and a register CSV
(`GenerateAttendanceRegisterExportAction`). **Still not built**: period/subject-mode marking in the
admin UI, and a statutory-format register (no statutory template is specified).

**ACA-05 note.** Built: `Grading\Scales` (contiguity-validated band
editor), `Assessment\Types`, `Assessment\Planner` (advisory live
weight total), `Marks\Entry` ⭐ (folds in submit + publish — one
lifecycle action bar), `Marks\Amend`, `Results\Compute` (the full
aggregation → position-recompute pipeline per class, with its own
weight-shortfall advisory report), `Results\Comments` (comment-bank
management only — applying a comment to a specific result has no
Action). **Deliberately not built, because the backend for them
doesn't exist at all** (verified: no Action anywhere generates,
withholds, publishes, or moderates a report card/transcript, confirmed
in `AmendMarkAction`'s own docblock) — Moderation, Report card run,
Withheld reports, Publication, Transcripts, Performance analytics. Also
found: `AC-ACA-05-001`'s weight-shortfall **block** is not actually
implemented anywhere in the domain layer despite being named as
checked in two Actions' own docblocks — this pass's `Results\Compute`
computes the shortfall itself and shows it as a non-blocking advisory.
See `.ai/rules/academic.md` for this and the `students.status` /
`CurriculumFrameworkFactory` default-code traps found along the way.

### Book E — Academic Depth — 🟡 in progress

| Module | Screens | Status |
|---|---|---|
| ACA-03 | Timetable & Scheduling Engine | 🟡 partial (see note) |
| ACA-06 | School-Based Projects & Legacy CALA | 🟡 partial (see note) |
| ACA-07 | Examinations Administration | 🟡 partial (see note) |

**ACA-03 note.** Built (`Livewire/Timetable/`, 12 screens): `Structures`
(create-only, bundles the slot set, like `CreatePeriodStructureAction`
itself does), `Venues`, `Constraints`, `Requirements` (read-only,
`BuildTimetableRequirementsAction`'s own list plus a feasibility
heuristic this screen computes itself — no Action produces one),
`Generate` (folds in the spec's own missing "create a timetable" step —
see the new `CreateTimetableAction` below — then runs
`GenerateTimetableAction` synchronously; no live progress/score curve/
cancel, since the action itself is a synchronous, deliberately
simplified greedy pass, not a queued annealing job), `Editor` (a class grid you drag lessons across, plus the add-one-slot form — gap-closing pass:
`MoveTimetableSlotAction`/`RemoveTimetableSlotAction` run the same four-level clash check and refuse
with the conflict named; last move can be undone; locked, double and published lessons do not move), `Clashes` (runs the real `TimetableClashDetector`),
`Views` (one filtered table standing in for the spec's five separate
by-class/teacher/venue/learner/department views; "printable" stops at
the browser's own print dialog — no export Action exists), `Publish`
(blocked while hard violations exist, then a separate explicit
attendance-session-generation call), `Cover` (daily substitutions,
`SuggestCoverAction`'s ranked suggestions, one-click assign),
`Exceptions`, `ExamPlanner` (disruption report).
**New gap-filling Action**: `CreateTimetableAction` — no Action
anywhere created the parent `Timetable` row before this pass (every
test fixture used the factory directly); added create-only, mirroring
the ACA-01 catalogue precedent from the Book D pass.
**Deliberately not built**: a clash panel that updates *during* the drag (a refused drop names the
conflict instead), queued generation with a cancellable
progress bar and a real simulated-annealing score curve (the backend
action itself doesn't implement these — see its own docblock), and any
PDF/export generation for timetable views. See `.ai/rules/academic.md`.

**ACA-06 note.** Built (`Livewire/Projects/`, 10 screens):
`Instruments`, `Briefs` (folds the spec's separate library+editor
screens into one, like `Curriculum\Frameworks`), `Rubrics` (criteria
weight-sum-to-100% guard), `Approve` (one lifecycle screen hosting both
HOD approve and issue), `Tracker` (progress grid + chase list +
`ExemptLearnerProjectAction`'s exemption action), `Mark` (marking queue,
criterion-by-criterion, no evidence viewer alongside the rubric — this
pass's screens are staff-marking-focused, not a document viewer),
`Moderate`, `Verify`, `Amend` (mirrors `Marks\Amend`'s own
`approved: true` boundary — no CORE-07 workflow wired up here either),
`CalaArchive` (read-only). **Deliberately not built**: Portfolio
compilation and the national submission export — no Action exists for
either (confirmed by grep); learner-facing milestone/evidence
submission has no admin screen since the spec itself places that
interaction on the mobile app/portal, not the staff console.
See `.ai/rules/academic.md`.

**ACA-07 note.** Built (`Livewire/Exams/`, 13 screens): `Sessions`
(+ the new gap-filling `AdvanceExaminationSessionStatusAction`, below),
`Papers` (live weight-% advisory), `PaperVault` (vet → seal → release
lifecycle + access log — `ReleaseExaminationPaperAction`'s own
no-override-for-anyone gate is exactly what AC-ACA-07-001 tests;
encryption-at-rest and visible watermarking are backend-documented
gaps, not built), `Candidates` (derive from enrolments, confirm),
`Seating` (auto-allocate; also stands in for the spec's separate
"Attendance sheets" screen — same seating + special-arrangement data,
one more column), `Invigilation` (subject-teacher exclusion, override
checkbox), `Scripts` (collect/handover, discrepancy alerts), `MarkEntry`
(blind double marking — structurally blind, since the Action itself
never returns the other marker's value), `Variance` (third marking),
`Moderate`, `Arrangements` (record/approve), `Malpractice` (confidential
— gated behind its own dedicated permissions, not the general
`exams.manage`), `Results` (process → publish, staged).
**New gap-filling Action**: `AdvanceExaminationSessionStatusAction` — a
small, forward-only status transition; nothing in the domain layer
ever moved a fresh session from `planning` to `in_progress`, which
`ProcessExaminationResultsAction` requires before it will run.
**Real bug found and fixed during this pass, not just documented**:
seven screens across all three ACA-07/06 modules originally passed
`Auth::id()` (a `users.id`) into an Action parameter documented and
typed as a `staff.id` (`ApproveProjectBriefAction`, `MarkProjectAction`,
`ModerateProjectAction`, `VetExaminationPaperAction`,
`EnterExamMarkAction`, `EnterThirdExamMarkAction`,
`ModerateExamMarkAction`), plus two more that passed a `staff.id` where
`HandoverScriptBatchData`/`CollectScriptBatchData` expected a
`recordedByUserId`. Every one is now resolved via
`Staff::where('user_id', Auth::id())` with an explicit "no staff record
linked" refusal if it comes back null, never a silent wrong-id write —
caught by this pass's own tests, not by a type error, since both ids
are plain `int`. **Deliberately not built**: `CMP-01`'s candidate-set
export interface, performance analysis/distributions (no Action
computes one), and `FIN-02` entry-fee billing — all confirmed backend
gaps. See `.ai/rules/academic.md`.

### Book F — Boarding & Welfare — 🟡 in progress

| Module | Screens | Status |
|---|---|---|
| BRD-01 | Hostel, Room & Bed Allocation | 🟡 partial (see note) |
| BRD-02 | Roll Call & Movement ⭐ | 🟡 partial (see note) |
| BRD-03 | Exeat, Leave & Visitor Management ⭐ | 🟡 partial (see note) |
| BRD-04 | Catering, Menus & Kitchen | 🟡 partial (see note) |
| BRD-05 | Laundry & Linen | 🟡 partial (see note) |

**BRD-01 note.** Built (`Livewire/Hostels/`, `Allocation/`, `Inspections/`,
`Damages/` — 9 screens): `Hostels\{Structure,Show}`, `Allocation\{Board,
Run,Waitlist,Constraints,Incompatibilities}`, `Inspections\Index`,
`Damages\Index`. `Allocation\Board` is a plain occupied/free bed table
(not the spec's own drag-to-move visual grid — the same trade-off
`ACA-03 Timetable\Editor` already made) but also stands in for the
spec's separate "Bed availability" report; `Board` folds in the spec's
own "Learner allocation" screen too (move/end actions operate directly
on the selected bed's current occupant). **Gender segregation has no
UI bypass anywhere** — `AllocateBedAction`/`MoveLearnerAction`/
`RunBulkAllocationAction` throw `GenderMismatchException` unconditionally
and no screen offers an override control, verified by a dedicated test.
**Three new, small, gap-filling Actions** (`CreateHostelWingAction`,
`CreateAllocationConstraintAction`, `CreateLearnerIncompatibilityAction`)
— mirroring the ACA-01 precedent, the backend had models/migrations/
factories for `hostel_wings`, `allocation_constraints`, and
`learner_incompatibilities` but no Action anywhere ever created a row
(verified by grep; every existing row came from `TenantModelRegistry`'s
own factory call). `Allocation\Constraints`'s dropdown deliberately
excludes `gender_match` as a configurable option — that constraint is
enforced unconditionally in the Action layer, never read from this
table. `Allocation\Incompatibilities` enforces the confidential-reason
tiered-visibility rule server-side: a confidential row's `reason` is
replaced with `[restricted]` before the view ever sees it, for a viewer
without `boarding.incompatibility.manage`.

**BRD-02 note ⭐.** Built (`Livewire/RollCall/`, `Movement/`,
`Occupancy/` — 8 screens): `RollCall\{Take,Board,Incidents,Incident,
Escalation}`, `Movement\{Log,Checkpoints}`, `Occupancy\Live`. `Escalation`
folds in the spec's separate "Roll call points" screen (no such screen
exists in the spec's own table — a point always references a profile).
`Board` also stands in for "Roll call history" (a date picker over the
same table). **The escalation ladder is built to the letter**:
acknowledging a step never stops the clock (`AdvanceEscalationLadderAction`
runs independently on elapsed time from `first_missed_at`), and a step
flagged `requires_action_record` cannot be satisfied by acknowledgement
alone — verified by a dedicated test that acknowledges step 1, attempts
to record an empty action, confirms it's refused, then confirms a real
free-text action record succeeds. A second test confirms
`MissingLearnerIncident::delete()` throws at the model's own database-
grant-enforcing `booted()` hook, for any caller. `AdvanceEscalationLadderAction`/
`CheckRollCallMissedAction` have no scheduled-command wiring yet (per
their own docblocks) — `Incidents`'s "Check ladder" button and
`RollCall\Board`'s own read are the honest on-demand stand-ins.
**New gap-filling Action**: `CreateMovementCheckpointAction` (same
"no Action ever created one" gap as BRD-01's wing/constraint/
incompatibility rows).

**BRD-03 note ⭐.** Built (`Livewire/Exeats/`, `Gate/`, `Visitors/` —
11 screens): `Exeats\{Index,Show,Approvals,Overdue,Types}`,
`Gate\{Terminal,Attempts}`, `Visitors\{Terminal,Log,Blacklist,
VisitingDays}`. `Exeats\Index` folds in the unbuilt guardian-portal
request form (staff-recorded via `request_source = phone_recorded`,
matching BR-BRD-03-001's own exception). `Exeats\Types` folds in the
spec's separate "Exeat quotas" screen as a read-only roll-up (no Action
pre-sets a quota ahead of a request — `ExeatQuota` rows are created
on-demand inside `RequestExeatAction` itself). **The gate terminal is
the single most safety-critical screen in this pass**: the result
renders as one word, large and colour-coded — `RELEASE` or
`DO NOT RELEASE` — exactly per spec, with zero override control,
because `RecordDepartureAction` itself has none. Verified by two
dedicated tests: an adult not named on the exeat is refused
(`no_right`, logged permanently to an append-only `collection_attempts`
row that itself refuses `delete()`), and a court-restricted guardian is
refused (`court_restriction`) even though they are named on the exeat
and hold a standing collection right — the restriction overrides every
other flag, per BR-BRD-03-013. A third test confirms a blacklisted
visitor is refused at `SignInVisitorAction` itself with no
`visitor_logs` row ever created for the attempt.

**BRD-04 note.** Built (`Livewire/Catering/` — 5 screens):
`MenuCycles` (folds in the spec's separate "Menu planner" — pick a
cycle, set each day's recipes, the same fold `Curriculum\Frameworks`
uses for its own banner), `Recipes`, `ServicePlan` (⭐ folds in the
spec's separate "Requisition & issue" screen — in this codebase's
sanctioned planning-only mode, §0.3, there is no real stock to issue/
return, so the fold costs nothing), `ServingTerminal`, `Dietary`.
**The per-capita scaling is real and verified**: a dedicated test
allocates two boarders, completes a roll call marking one present and
one missing, plans a lunch service, and confirms `nominal_boarders`
stays 2 while `present_boarders` is 1 — servings are computed from the
live roll, never the allocated-bed count (BR-BRD-04-001). A second test
confirms a life-threatening dietary alert is recorded unverified, then
verified only through the explicit nurse-verification step
(BR-BRD-04-010) — recording one never marks it verified by itself.
Cost columns read `unavailable`, never `0`, in this planning-only mode
(`NullStoreIssuanceProvider`). Gap-closing pass: catering is now costed — `StoresIssuanceProvider` replaces the null provider (standard
cost, else the latest priced lot; availability from lots, `null` when there is no stock record), requisition
lines carry their cost, and closing a service computes its cost from fully priced ingredients scaled to the
servings served. `Catering\Costs` shows cost per meal, per week and over-production; unpriced services are
counted apart, never shown as zero. **Still not built**: `PublicMenu` (a portal screen) and a dedicated
meal-attendance capture UI (`catering.meal_attendance_capture` defaults off).

**BRD-05 note.** Built (`Livewire/Linen/`, `Laundry/` — 5 screens):
`Linen\{Items,Issue,Clearance}`, `Laundry\{Cycles,Missing}`. `Linen\Issue`
folds in the spec's separate "Learner items" screen (picking a learner
already shows their full item list) and hosts the damage/lost
report-then-approve lifecycle inline. **Clearance blocking is real and
verified**: a dedicated test issues an item, confirms `Linen\Clearance`
blocks (`CheckLinenClearanceAction`'s own query), returns the item via
`ReturnIssuedItemAction`, and confirms clearance then clears
(AC-BRD-05-001). `CheckLinenClearanceAction` remains deliberately NOT
wired into `WithdrawStudentAction` in this pass, per that action's own
docblock.

**Backend gaps found during this pass, all documented in-code**: no
Action anywhere created `hostel_wings`, `allocation_constraints`,
`learner_incompatibilities`, or `movement_checkpoints` rows before this
pass's four small gap-filling Actions (see BRD-01/02 notes above) — in
every case the model/migration/factory existed but only
`TenantModelRegistry`'s own tenancy-isolation-test fixture ever created
one. See `.ai/rules/boarding.md` for the full list of what was built,
deferred, and found.

### Book G — Welfare & Pastoral — 🟡 in progress

| Module | Screens | Status |
|---|---|---|
| BRD-06 | Health, Clinic & Sanatorium 🔒 | 🟡 partial (see note) |
| BRD-07 | Discipline, Conduct & Behaviour | 🟡 partial (see note) |
| BRD-08 | Counselling & Safeguarding 🔒🔒 | 🟡 partial (see note) |

**BRD-06 note 🔒.** Built (`Livewire/Health/`, 13 screens): `Record`
(folds "Clinical record" + "Condition register" — Tier 3, gated through
`ResolveMedicalTierAction` itself, not merely the permission flag),
`CarePlan` (Tier 2 — only `public_summary` ever reaches its condition
picker, never `name`/`diagnosis_notes`), `Alerts` (Tier 2 board, same
Tier-2-only field discipline), `SickBay` (folds "Observations"),
`MedicationRound` (the one-tap record — every consent/expiry/witness
refusal shown is `AdministerMedicationAction`'s own, never a client-side
guess), `Prescriptions`, `Consents`, `Immunisations`, `Incidents`,
`Referrals` (folds make → return → charge), `Stock` (folds "Controlled
register" — the same catalogue, filtered), `Outbreak` (new, aggregate-
only read, no Action backs it — safe since it never selects a
per-learner clinical field), `Screenings`. Consultations (gap-closing pass): `Health\Record` records them through `RecordConsultationAction`
(Tier 3, complaint/assessment/plan encrypted at rest, a visiting practitioner must be named). See
`.ai/rules/welfare.md`.

**BRD-07 note.** Built (`Livewire/Behaviour/`, `Sanctions/`,
`Detentions/`, `Committee/`, `Appeals/`, `Leadership/`, 14 screens):
`Behaviour\{Categories,Record,Learner,Board,Review,Rules,Analytics}`,
`Sanctions\{Types,Index,Issue}`, `Detentions\Register`,
`Committee\Hearing`, `Appeals\Index`, `Leadership\Index`. `Learner`
folds in "Conduct grades" (the computed grade is just that student's
own current-term balance). `Board`/`Review`/`Learner` all mask a
safeguarding-paused (`is_confidential`) record's category/points,
showing only "under review" — verified by a dedicated test.
`Categories`' deactivate button surfaces
`DeactivateBehaviourCategoryAction`'s own refusal to empty the last
active trigger category, verified by a dedicated test. `Sanctions\Issue`
surfaces `IssueSanctionAction`'s own committee/boarding-arrangement
refusals as toasts, never a silent create. **Deliberately not built**:
a sports-fixture clash check for detentions — no fixture/timetable
table exists yet (`OPS-07`), matching `ScheduleDetentionAction`'s own
documented gap.

**BRD-08 note 🔒🔒 — read before touching anything in this module.**
Built (`Livewire/Safeguarding/`, `Livewire/Counselling/`, 9 screens):
`Safeguarding\{Report,Triage,Cases,CaseDetail,Grants,Vulnerable,
Reviews,Audit}`, `Counselling\Diary`. Named `CaseDetail`, not the
spec's own bare `Case` — `case` is a PHP reserved keyword (the same
trap `PPL-04`'s `Exit` already hit). `CaseDetail::mount()` is the ONLY
place any screen reads case content, and it does so by calling
`ViewSafeguardingCaseAction::execute()` exactly once — vendor/
impersonation hard-excluded first (closed over a real gap found during
this pass: during impersonation `Auth::user()` resolves to the
*impersonated* user, so an ordinary permission check alone does not
stop a vendor engineer impersonating a legitimately-permissioned staff
member — `ImpersonationContext::current()` must be read and passed to
the Action explicitly, which `CaseDetail`/`Grants` now both do, and a
new `BlocksVendorAndImpersonation` trait does the same plain-403
version for every other screen in this module that touches a concern,
case-adjacent dashboard, or counselling session with no single case to
hand the Action). `Cases` (the list) is deliberately conservative: it
shows only `case_reference`/student/`status`, filtered to the lead's
full view or a granted user's own cases — never `category`/
`risk_level`/`summary`, which stay behind the one audited
`ViewSafeguardingCaseAction` call on `CaseDetail`. `Grants` additionally
confirms the actor IS the lead (not merely has a grant) before allowing
any grant/revoke, since `GrantCaseAccessAction` doesn't re-derive that
itself. **No delete control exists anywhere in this module's screens or
views for a concern or a case — verified by a dedicated test that
scans every file in `Livewire/Safeguarding/` and
`resources/views/safeguarding/` for the string.** `Report` supports
both named and anonymous submission (the anonymous path stores no
reporter id — structurally, not merely by omission) plus a token
follow-up lookup. `Reviews` is deliberately more conservative than
strictly required: a risk assessment's own `risk_level`/`rationale`
never reach it, only the bare `case_id` reference and due date, because
BR-BRD-08-005 says "every read... reads, not just writes" with no lead
carve-out and this dashboard has nowhere to log a per-row read.
`Counselling\Diary` is filtered to the signed-in counsellor's own
`counsellor_staff_id` only — a lead-wide view across counsellors was
deliberately not built (BR-BRD-08-015's own "not to the head by
default" plus no audited per-session read path existing to build it
safely on). **Judgment calls made under genuine uncertainty, not
guesses**: (1) `Safeguarding\Triage` reads `SafeguardingConcern.description`
directly, gated only by `safeguarding.lead`/`.deputy_lead` — no
"ViewSafeguardingConcernAction" exists in the domain layer the way
`ViewSafeguardingCaseAction` exists for cases, and the spec's own
screen-access column names the role directly for pre-case concerns
("Triage queue | safeguarding lead"), unlike a case's per-grant model —
treated as the sanctioned enforcement for concerns specifically,
documented inline in `Triage`'s own docblock; (2) `CaseDetail` treats
reading a case's own entries/risk-assessments/referrals as covered by
the one case-level audited read rather than one audited read per row,
since no per-entry read Action exists — if ever judged too loose, add
a per-entry audit call, don't loosen the case-level check. See
`.ai/rules/welfare.md` for the full reasoning on both.

### Book H1 — Procurement, Stores, Assets, Budgets — 🟡 in progress
FIN-08–FIN-11 all live in **`Modules/Stores`** (verified: every FIN-08–11
migration's own docblock attributes to it), not `Modules/Finance`.

| Module | Screens | Status |
|---|---|---|
| FIN-09 | Inventory, Stores & Requisitions ⭐ | 🟡 partial (see note) |
| FIN-08 | Procurement, Suppliers & AP 🇿🇼 | 🟡 partial (see note) |
| FIN-10 | Fixed Assets & Depreciation | 🟡 partial (see note) |
| FIN-11 | Budgeting & Commitment Accounting ⭐ | 🟡 partial (see note) |

**FIN-09 note ⭐.** Built (`Livewire/{Stores,Items,Stock,Receipts,
Requisitions,Transfers,StockTake,Anomalies,Reports}/`, 16 screens):
`Stores\Index`, `Items\Index` (also stands in for the spec's separate
"Item editor" — no `UpdateInventoryItemAction` exists), `Stock\{OnHand,
ItemLedger,Expiry,SellToLearner}`, `Receipts\Create`, `Requisitions\
{Create,Issue,ReturnItems}` (named `ReturnItems`, not the spec's own
bare `Return` — `return` is a PHP reserved keyword, the same trap
`PPL-04`'s `Exit`/`BRD-08`'s `Case` already hit), `Transfers\Index`
(dispatch+receive+discrepancy, one screen), `StockTake\{Count,
Variance}` (blind count sheet built exclusively from
`GetBlindCountSheetAction`'s own `BlindCountLine` DTO, which has no
field to carry `system_quantity` even if the screen tried — verified
by a dedicated test), `Anomalies\Index` (folds baseline computation in
alongside detection and investigation), `Reports\{Valuation,
Consumption}`. A dedicated test reproduces AC-FIN-09-001 itself
through the admin screens (two lots at $0.80/$0.95, 150-unit issue,
one $127.50 journal). **Deliberately not built**: a dedicated screen
for `RebuildStockBalanceAction` as a dispatchable job trigger (folded
into `Stock\OnHand` as a per-item "Rebuild" button instead) and any
scheduled-command wiring for the expiry/reorder/anomaly checks (the
Actions are real and run on-demand from their own screens; the cron
entries are a documented backend gap per `StoresServiceProvider`'s own
docblock). **New gap-filling Action**: `RecordStockTakeVarianceReasonAction`
— `StockTake\Variance`'s own "save reason" control originally wrote
`StockTakeLine::update()` directly; no Action anywhere in the domain
layer ever set just that one field even though
`ApproveStockTakeVarianceAction` requires it before approval. Caught
by `ActionPatternEnforcementTest` (BR-GLOBAL-005) on the first
whole-app test run, fixed with the narrowest possible Action rather
than widening the CI rule.

**FIN-08 note 🇿🇼.** Built (`Livewire/Procurement/{Suppliers,
Requisitions,Quotations,Orders,Receipts,Invoices,Payments,Reports}/`,
12 screens): `Suppliers\{Index,Show,BankChange,Clearances}`
(`BankChange` stages a proposed change in cache keyed by supplier,
since no `supplier_bank_change_requests` table exists in the domain
layer — `ChangeSupplierBankDetailsAction` itself is what refuses a
same-user request+approve, this screen only avoids letting one form
double as its own approval button), `Requisitions\Index` (budget
indicator read from `budget_check_result`/`budget_available_minor`,
real values the Action already recorded), `Quotations\Compare`
(request→record→award, one screen), `Orders\Index` (folds the spec's
separate "Create order" screen in — create+list+approve+cancel+
close-short, with a live budget-impact preview), `Receipts\Create`
(GRN), `Invoices\Register` (🇿🇼 the non-fiscal-VAT-exposure banner is
computed independently in the screen as an advisory, matching
`RegisterSupplierInvoiceAction`'s own stored `input_vat_claimable`
figure — never a substitute for it), `Invoices\MatchReview` (named
`MatchReview`, not the spec's own bare `Match` — `match` is a PHP
reserved keyword), `Payments\Run`, `Reports\Index` (folds the spec's
four separate report screens — Aging, Unclaimable VAT, Withholding,
Spend — into one tabbed screen). A dedicated test proves withholding
applies at the configured rate with no tax clearance on file
(AC-FIN-08-001). Contracts (gap-closing pass): `Procurement\Contracts\Index` with
`CreateSupplierContractAction`, `RenewSupplierContractAction` and `TerminateSupplierContractAction`;
the existing expiry job keeps alerting.

**FIN-10 note.** Built (`Livewire/Assets/{Register,Depreciation,
Verification,Disposal,Insurance,Reports}/`, 8 screens): `Register\
{Index,Show}` (`Index` also stands in for the spec's separate "Create
asset" screen — `CapitalizeAssetAction` is the one entry point for
every capitalisation source; `Show` folds in the spec's separate
"Transfer" screen as one more action-bar card), `Depreciation\Run`
(preview→approve→post), `Verification\{Round,Discrepancies}` (`Round`
creates one row per active asset up front so a never-scanned asset
stays visibly pending, never silently absent), `Disposal\Create`,
`Insurance\Index`, `Reports\Reconciliation`. A dedicated test proves a
not-found asset cannot be written off by the same user who recorded
the failed scan, and CAN be by a different one (BR-FIN-10-012,
AC-FIN-10-005). Automatic capitalisation (gap-closing pass): a capital PO line and
a capitalisable item each carry an optional `asset_category_id`; the FIN-08
`CapitalPurchaseReceived` and FIN-09 `ItemCapitalisationDue` events now create one
asset per whole unit and reclassify the expense. Without a category, capitalisation
stays manual.

**FIN-11 note ⭐.** Built (`Livewire/Budget/{Builder,Consolidation,
Variance,Commitments,Virement,Forecast}/`, 6 screens): `Builder\Index`
(folds the spec's separate "Departmental submission" screen in — both
read/write the same `SubmitBudgetLineAction`, differing only by which
cost centres a `budget.submit`-only viewer may touch), `Consolidation\
Review`, `Variance\Dashboard` (⭐ "Recalculate actuals" is the ONLY
control that ever changes `actual_minor`, and it only ever re-sums
real `journal_lines` — BR-FIN-11-008), `Commitments\Index` (read-only),
`Virement\Create` (request+approve, one screen), `Forecast\Index`
(folds the spec's three separate Fee income/Cash flow/Scenarios
screens into one `forecast_type`-tagged form). A dedicated test
exercises the real cross-module wiring end to end: approving a
purchase order through `Procurement\Orders\Index` immediately drops
the linked budget line's `available_minor` by the order total, through
the actual `PurchaseOrderApproved` → `CreateBudgetCommitmentOnPurchaseOrderApprovedListener`
event chain, not a simulated call (AC-FIN-11-001). Forecast maths (gap-closing
pass): `ProjectFeeIncomeAction` projects fee income per term from a reference year's
actual invoicing and collection (growth, fee-increase and collection-rate assumptions)
and `ProjectCashFlowAction` combines receipts with payroll and open commitments; the
Forecast screen's "Compute from actuals" fills the scenario. It projects from FIN-03
actuals, not by re-pricing each learner from the FIN-02 fee structure.

See `.ai/rules/stores.md` for the full reasoning, the permission
module-code split (`INVENTORY`/`PROCUREMENT`/`ASSETS`/`BUDGET`, not one
`STORES` call), and testing gotchas found along the way.

### Book H2 — Operations & Estates — 🟡 all 7 modules shipped (stays below ✅ only because OPS-02's own prior deferred screens, not this pass)
Verified module ownership (one Laravel module each): OPS-01 → `Transport`,
OPS-02 → `Operations`, OPS-03 → `Farm`, OPS-04 → `Utilities`, OPS-05 →
`Facilities`, OPS-06 → `Security`, OPS-07 → `Sport`. The first pass covered
OPS-01/02/03, in the book's own build order (OPS-02 → OPS-01 → OPS-03,
§0.2). **This second pass covers OPS-04 (Utilities), OPS-05 (Facilities),
OPS-06 (Security), and OPS-07 (Sport)** — independent of each other and of
the first three per the book's own build order (§0.2 lists OPS-05/06/07 as
"independent; parallel"), built here in spec reading order OPS-04 → OPS-05
→ OPS-06 → OPS-07. All seven modules now have a populated `Livewire/`
directory. The book-level status stays 🟡 rather than ✅ only because
OPS-02's own note below (from the first pass) documents two deliberately
deferred screens — per this file's own honesty discipline, ANY module with
a deferred item keeps the whole book below ✅; nothing in this second pass
left a comparable gap (see each of the four new notes below).

| Module | Screens | Status |
|---|---|---|
| OPS-02 | Maintenance & Works Management | ✅ (see note — 2 screens deliberately deferred) |
| OPS-01 | Transport & Fleet Management 🇿🇼 | ✅ |
| OPS-03 | Estates, Farm & Production Units 🇿🇼 | ✅ |
| OPS-04 | Utilities & Energy Management 🇿🇼 | ✅ |
| OPS-05 | Facilities & Hire | ✅ |
| OPS-06 | Security & Access | ✅ |
| OPS-07 | Sport, Houses & Co-curricular | ✅ |

**OPS-02 note.** Built (`Livewire/Maintenance/{Assets,Reports,Schedules,
WorkOrders}/`, `Livewire/Maintenance/{Report,Triage}.php`,
`Livewire/Projects/`, 7 screens): `Maintenance\Report` (any authenticated
user, no permission of its own — the spec's own screen table names its
permission literally "any user"), `Maintenance\Triage` (safety-flagged
reports always sort first, BR-OPS-02-002), `Maintenance\WorkOrders\Index`
(folds the spec's separate "Technician job card" screen into one
list+detail action bar — approve/complete/verify/issue parts/record
labour/record contractor cost all operate on the selected order),
`Maintenance\Assets\Index` (also stands in for "Asset maintenance
history"), `Maintenance\Schedules\Index` (preventive schedules + an
on-demand "Generate due" button, since `GeneratePreventiveWorkOrdersAction`
has no cron wiring yet), `Maintenance\Reports\Index` (folds the spec's
separate SLA and Cost analysis screens into one tabbed read),
`Projects\Index` (capital projects, create/advance/complete). **Three new
gap-filling Actions**: `CreateMaintenanceAssetAction`,
`CreateMaintenanceScheduleAction` (same "model/migration/factory exist,
no Action ever created a row" gap prior books hit repeatedly — verified
by grep), and `AdvanceCapitalProjectStatusAction` (forward-only
planning→approved→in_progress; nothing in the shipped domain layer moved
a capital project out of `planning`, and `CompleteCapitalProjectAction`
requires `approved`/`in_progress`). Gap-closing pass: `Projects\Index` now has a Details panel to name the main contractor
(`AssignCapitalProjectContractorAction`, active suppliers only) and to add and complete milestones
(`AddCapitalProjectMilestoneAction`, `CompleteCapitalProjectMilestoneAction`). **Still not built**: a
standalone "Contractor management" screen — nothing distinguishes a contractor supplier from any other.

**OPS-01 note 🇿🇼.** Built (`Livewire/{Fleet,Compliance,Drivers,Routes,
Assignment,Trips,Manifest,Fuel,FuelAnomalies,Incidents,RouteCosts}/`, 11
screens): `Fleet\Index` (register + ground/reactivate), `Compliance\Index`
🇿🇼 (the seven statutory compliance types from BR-OPS-01-002; the
`[60,30,7]`-day alert window is read live from
`transport.compliance_alert_days` via `SettingResolver` — never a
hard-coded literal in the screen, per CLAUDE.md's statutory-figure rule),
`Drivers\Index`, `Routes\Index` (folds zone management in alongside
route+stop creation — the spec names no separate "Zones" screen),
`Assignment\Index` (shows the resulting termly fee live, read off the
picked stop's own zone), `Trips\Index` (schedule + depart + odometer),
`Manifest\Show` (tap-to-board/alight), `Fuel\Index`, `FuelAnomalies\Index`
⭐ (never dismissed without a recorded explanation; a "Run 30-day check"
button for `CheckCumulativeFuelAnomalyAction`, uncronned), `Incidents\Index`,
`RouteCosts\Index`. **One new gap-filling Action**: `ReactivateVehicleAction`
— `GroundVehicleAction` shipped with no reverse, which would have left
the Compliance monitor's own "grounded vehicles" view a one-way trip.
A dedicated test proves `ScheduleTripAction` itself (never duplicated in
the UI) refuses a vehicle with an expired certificate of fitness
(AC-OPS-01-001), and `Assignment\Index` shows the correct termly fee
before confirming (AC-OPS-01-002).

**OPS-03 note 🇿🇼.** Built (`Livewire/{Units,Fields,Cycles,Harvest,
Livestock,LivestockEvents,Production,KitchenTransfers,Sales,Reports}/`, 10
screens): `Units\Index`, `Fields\Index`, `Cycles\Index` (folds the spec's
separate "Input recording" screen into the selected cycle's own action
bar alongside labour/overhead/fail), `Harvest\Index` (shows cost per kg —
the internal transfer price — immediately after recording, read back off
the cycle `RecordHarvestAction` just updated), `Livestock\Index`
(capitalisation fields only shown for `purpose = breeding`, mirroring
`CreateLivestockAction`'s own `shouldCapitalize()` gate), `LivestockEvents\Index`
(withdrawal period captured directly on a treatment), `Production\Index`,
`KitchenTransfers\Index` ⭐ — **named `KitchenTransfers`, not the spec's own
bare `Transfers`: a real cross-module Livewire component-name collision
was found and fixed in this pass** (see below), `Sales\Index`,
`Reports\Index` (folds the spec's separate "Profitability" and "Savings
report" screens into one tabbed read). **Deliberately not built**: no
screen fabricates fiscalisation — `farm_sales.fiscal_receipt_id` stays
null on every row `Sales\Index` creates, since `FIN-13` (Book H3) doesn't
exist yet (`RecordFarmSaleAction`'s own documented boundary); the spec's
own AC-OPS-03-006 ("fiscalised through FIN-13") is therefore not
literally true today, recorded here rather than silently improvised past.
Two dedicated tests prove AC-OPS-03-001 (a 1,440/1,800kg cycle yields
exactly 80 minor-unit cost per kg) and AC-OPS-03-003 (a kitchen transfer
of milk from a cow within a recorded withdrawal period is blocked, naming
nothing extra — the Action's own message already names the end date).

**A real, previously-undiscovered bug found and fixed in this pass:
Livewire full-page components collide by name across modules when two
different modules each register a component at the same path relative
to their own `Livewire::addLocation()` root.** `Modules\Farm\Livewire\Transfers\Index`
(this pass's original name, matching the spec's own screen name) and
`Modules\Stores\Livewire\Transfers\Index` (Book H1 FIN-09, already
shipped) are both exactly `Transfers/Index.php` one level under their
module's own `Livewire/` root. Livewire's component Finder resolves a
full-page route's component by a name derived from this relative path,
not the fully-qualified PHP class — so both routes silently resolved to
whichever module registered its service provider later, and a user
granted only `farm.transfer` got a plain `403` on `farm.transfers.index`
(checked against Stores' `inventory.transfer.manage` instead), with no
exception logged and the real component's own `mount()` never invoked —
confirmed by direct instrumentation. Fixed by renaming the class to
`KitchenTransfers\Index` (end to end: namespace, directory, view path,
route import, test import) rather than touching Stores' earlier-shipped
screen. **Before naming any new module-root Livewire component** (one
directly under `Livewire/`, not nested under a sub-namespace), check
`find . -name Index.php | grep Livewire | sed -E 's#.*/Livewire/##; s#/Index\.php$##' | sort | uniq -d`
for a collision first — this is now a standing check, not a one-off.

**OPS-04 note 🇿🇼 (second pass).** Built (`Livewire/{Accounts,Meters,
Tokens,Readings,Generators,GeneratorRuns,Solar,Water,LoadShedding,
Dashboard}/`, 10 screens): `Accounts\Index`, `Meters\Index`,
`Tokens\Index` ⭐ (purchase, confirm credit, uncredited queue, monthly
reconciliation — the real control that recovers money nobody would
otherwise notice went missing), `Readings\Index` (append-only, anomaly
flagged live), `Generators\Index`, `GeneratorRuns\Index` (start/stop,
real diesel draw through `FIN-09`), `Solar\Index`, `Water\Index`
(sources/readings/quality folded into one register), `LoadShedding\Index`,
`Dashboard\Index` ⭐ (folds the spec's separate "Consumption analysis"
screen into this one read, alongside the board-ready cost-of-outage
sentence from `OutageCostResult`). **No gap-filling Actions needed** —
every table already had a real create Action. One Livewire-hydration
gotcha hit and fixed: a plain readonly DTO (`OutageCostResult`) cannot
be a public Livewire property (`Property type not supported in
Livewire`); exploded into scalar properties instead — see
`.ai/rules/utilities.md`.

**OPS-05 note.** Built (`Livewire/{Resources,Calendar,Request,Hire,
Utilisation}/`, 5 screens): `Resources\Index` ⭐ (gap-fill — the spec's
own screen table names no screen that creates a `BookableResource`
row, even though `CreateBookableResourceAction` exists), `Calendar\Index`
(read-only week view), `Request\Index` ⭐ (live clash check before
submit, cancel, recurring expansion), `Hire\Index` ⭐ (folds the
spec's own, separate "Approvals" screen into the external hire
lifecycle — approve, deposit, confirm, complete, assess damage &
refund — since the only thing `facilities.approve` ever approves in
this book is an external hire), `Utilisation\Index`. A dedicated test
proves AC-OPS-05-001 against a genuinely published `Modules\Academic`
timetable (not a stub) — see `.ai/rules/facilities.md` for the
cycle-day-1 fixture trick that makes this reliable on any weekday.

**OPS-06 note ⭐⭐.** Built (`Livewire/{Muster,OccurrenceBook,Patrols,
Contractors,Keys,LostProperty,Drills}/`, 7 screens): `Muster\Index` ⭐⭐
(trigger a drill, live roster from `AssembleMusterRollAction`,
tap-to-mark present, complete — the single most operationally
important screen in this module), `OccurrenceBook\Index` (append-only,
gapless, no edit/delete control anywhere), `Patrols\Index`,
`Contractors\Index` ⭐ (folds the gate sign-in/out hard-refusal flow in
— checked for and found a real structural match to
`Boarding\Gate\Terminal`/`Welfare\Safeguarding\CaseDetail`: no
override control exists on this screen because `SignInContractorWorkerAction`
itself has none), `Keys\Index`, `LostProperty\Index`, `Drills\Index`.
**Two gap-filling Actions added**: `CreateKeyAndCardAction` (no Action
anywhere ever created a `keys_and_cards` row before this pass — only
issue/return existed) and `RecordDrillFindingsAction` (nothing wrote
`emergency_drills.findings`/`.actions_required`). A dedicated test
proves AC-OPS-06-004 (a contractor worker with an approved contractor
but no police clearance on file is refused gate access by name, no
override, no `ContractorSiteVisit` row created). See
`.ai/rules/security.md`.

**OPS-07 note ⭐⭐ — the final module of Book H2.** Built
(`Livewire/{Activities,Membership,Teams,Fixtures,Houses,Awards,Equipment}/`,
7 screens): `Activities\Index`, `Membership\Index`, `Teams\Index`,
`Fixtures\Index` ⭐ (folds the spec's separate "Squad selection" and
"Results" screens into this fixture's own action bar: schedule,
confirm — real `OPS-01` trip for away, real `OPS-05` booking for home
— select squad with medical clearance enforced, mark `BRD-02` roll
status `fixture`, record result, record injury), `Houses\Leaderboard`
⭐⭐ (folds house-competition creation and manual points into the
leaderboard read), `Awards\Index`, `Equipment\Index` (gap-fill — no
screen named for `IssueEquipmentAction`/`ReturnEquipmentAction`/
`CheckOverdueEquipmentAction`). **A second real cross-module Livewire
component-name collision was found and fixed in this pass**:
`Houses\Index` (the name a literal reading of the spec gives this
screen) collides with `Modules\Core\Livewire\Houses\Index` (Book A
CORE-02's own house register, already shipped) — fixed by renaming to
`Houses\Leaderboard` end to end, which also happens to match the
spec's own full component name (`Ops\Houses\Leaderboard`) better than
the bare `Index` would have. The standing duplicate-name check was run
again after the rename and is clean across the whole codebase. A
dedicated test proves AC-OPS-07-001 (a learner with a declared,
uncleared `affects_physical_activity` condition is blocked from squad
selection by name; the fixture's own `squad_student_ids` stays null,
not partially populated). See `.ai/rules/sport.md`.

Combined test count for this second pass: **47 new admin-UI tests**
across the four modules (13 Utilities + 9 Facilities + 12 Security +
13 Sport), all green, alongside the whole-app suite.

### Book H3 — Payroll, Fiscalisation & Compliance — 🟡 all 8 modules built, book stays 🟡 (PPL-05/FIN-13/FIN-12 each carry a deliberately-deferred item)
Verified module ownership: PPL-05 → `Payroll`, FIN-12 → `Reporting`, FIN-13
→ `Fiscal`, FIN-14 → `Wallet`, CMP-01–CMP-04 → `Compliance`. The first
pass covered the four FIN/PPL modules, in the book's own build order
(PPL-05 → FIN-13 → FIN-14 → FIN-12, §0.3 — "largest; independent of
the rest" first, reporting last since it "needs everything else
posting correctly"). **A second pass then built CMP-01–04
(`Modules/Compliance`)**, completing the book. The book as a whole
stays 🟡, not ✅ — PPL-05, FIN-13 and FIN-12 each already carry their
own documented deferred item (see their notes below), and CMP-01–04
turning out clean doesn't change that; this file's own "how to keep
this file honest" rule is to mark partial explicitly rather than round
up.

| Module | Screens | Status |
|---|---|---|
| PPL-05 | Payroll & Statutory Deductions 🇿🇼 | 🟡 partial (see note) |
| FIN-13 | ZIMRA Fiscalisation (FDMS) 🇿🇼 | 🟡 partial (see note) |
| FIN-14 | Student Wallet & Tuckshop | ✅ |
| FIN-12 | Financial Reporting & Period Close ⭐ | 🟡 partial (see note) |
| CMP-01 | ZIMSEC Candidate Registration & Results 🇿🇼 | ✅ |
| CMP-02 | MoPSE Returns & EMIS Reporting 🇿🇼 | ✅ |
| CMP-03 | Data Protection, Consent & Privacy 🇿🇼 | ✅ |
| CMP-04 | Policy, Document Register & Retention | ✅ |

**PPL-05 note 🇿🇼.** Built (`Livewire/{Statutory,Grades,Components,
Staff,Loans,Run,Payslips,Returns,Reports}/`, 11 screens):
`Statutory\Config` (the only screen that ever writes a PAYE band,
AIDS Levy, NSSA POBS/APWCS, ZIMDEF or NEC rate — a raw validated-JSON
textarea, not a structured band editor, with the live
`requires_confirmation` banner `Academic\Curriculum\Frameworks`
already established the pattern for), `Grades\Index` (also hosts the
new `CreatePayGradeNotchAction`), `Components\Index` (tax-treatment
flags, dropdown limited to the two calculation methods
`PayComponentResolver` actually supports), `Staff\Structure` (dated,
also hosts `AddStaffPayComponentAction`), `Loans\Index`, `Run\Wizard`
⭐ (folds the spec's own separate "Preview" screen into one
compute → approve → post → distribute lifecycle; resolves 14 of the
15 `PayrollGlAccounts` by a dedicated `payroll_*` `system_key`, the
fifteenth — Fee Debtors — from the `is_control_account`/
`subledger_type = 'student'` dropdown), `Run\BankFile` (generates the
bank CSV, records payment), `Payslips\Show` (calculation trace,
gated behind `people.staff.view_compensation` the same way
`People\Staff\Show` already gates salary), `Returns\Index`,
`Returns\Itf16`, `Reports\Summary` (named `Summary`, not the spec's
own bare `Index` — `Modules\Farm\Livewire\Reports\Index` already
occupies that path). **A real, pre-existing backend bug found and
fixed in this pass**: `StatutoryConfigResolver::find()` (plus two
more call sites sharing the same root cause,
`ComputePayslipAction::activeStructure()` and
`PayComponentResolver::resolve()`) compared a `date`-cast column
against a bare `Y-m-d` string with a plain `where(...)`; on SQLite
that column stores a full `Y-m-d 00:00:00` timestamp, so the
comparison silently excluded a configuration effective exactly
*today* — found only because this pass's own admin-UI test asserted
a newly-configured PAYE rate actually changed the computed
deduction, not merely that the row existed. Fixed with
`whereDate(...)`. See `.ai/rules/payroll.md`.

**FIN-13 note 🇿🇼.** Built (`Livewire/{Devices,Rules,Days,Receipts,
Queue,Reports,Reconciliation,Audit}/`, 9 screens): `Devices\Index`
(folds in Certificate lifecycle — no renewal path distinct from
registering a fresh device exists), `Rules\Index` (the routing
engine, rationale + reviewer required at creation), `Days\Index`
(open/close + compile Z-report once closed), `Receipts\Index`
(read-only monitor), `Receipts\Retry` ⭐ (failed/rejected retry, plus
manual credit-note raising for a source module that doesn't wire
`RaiseFiscalCreditNoteAction` automatically), `Queue\Status`
(offline depth/oldest/drain), `Reports\ZReports` (read-only),
`Reconciliation\Index` ⭐ (runs the real
`ReconcileFiscalisationAction` on demand), `Audit\Index` (read-only).
The audit log (BR-FIN-13-012) is written by `AuditedFiscalGatewayDriver`, a decorator that logs
every gateway request and response (gap closed in the post-Book-K pass). See
`.ai/rules/fiscal.md`.

**FIN-14 note.** Built (`Livewire/{Pos,Products,SpendPoints,Wallets,
TermEnd,Reports}/`, 8 screens): `Pos\Terminal` ⭐ (touch-grid sale,
an explicit "sale happened offline" toggle standing in for a real
browser-side offline cache, void for the operator's own recent
sales), `Products\Index`, `SpendPoints\Index`, `Wallets\Index`/
`Wallets\Show` (named `Wallets`, not the spec's own `Accounts` —
`Modules\Utilities\Livewire\Accounts\Index` already occupies that
path; `Show` hosts top-up, guardian-set controls and close),
`TermEnd\Process` ⚠ (preview-then-commit, no code path exists to
recognise a balance as income), `Reports\Reconciliation` ⭐ (runs
`ReconcileWalletLiabilityAction` on demand), `Reports\Sales`
(read-only). A wallet's liability account resolves by the
`wallet_liability` `system_key`; Fee Debtors reuses the same
`is_control_account`/`subledger_type = 'student'` dropdown PPL-05's
`Run\Wizard` uses. No gap-filling Actions were needed. See
`.ai/rules/wallet.md`.

**FIN-12 note ⭐.** Built (`Livewire/{Financial,Close,Schedules,
Export}/`, 5 screens — only screens with a real Action behind them;
the spec's own Departmental/Collection/PriorPeriod/Board have none,
verified by grep, and are not built; BalanceSheet and CashFlow were added
in the gap-closing pass, see below):
`Financial\TrialBalance`, `Financial\IncomeStatement` (folds the
spec's own separate "Point-in-time" screen in), `Close\Checklist`
(folds the spec's own separate "Close pack" screen in; a blocking
check never shows an acknowledge control at all), `Schedules\Index`
(also hosts the new `CreateReportDefinitionAction`),
`Export\Accounting` ⚠. **A second occurrence of PPL-05's own
date-comparison bug, found and fixed the same way**:
`GenerateAccountingExportAction`'s overlap check. Permissions
registered under module code `REPORTING`, diverging from the spec's
own literal `finance.report.*`/`finance.period.close` strings — the
same divergence `Modules\Stores` already established for FIN-08–11.
See `.ai/rules/financial-close.md` (named to avoid an unrelated
tooling filter on the substring "report" in a rule filename — the
module it documents is `Modules/Reporting`).

Combined test count for the first pass: **12 new admin-UI tests**
across the four modules (3 Payroll + 3 Fiscal + 3 Wallet + 3
Reporting), all green, alongside the whole-app suite.

**CMP-01 note 🇿🇼.** Built (`Livewire/Zimsec/{Registrations,Validation,
Fees,Export,Statements,ResultsImport,Analysis}/`, 7 screens, matching
the spec's own §4 table exactly): `Registrations\Index` (create/derive/
close; deadline countdown computed inline from `registration_closes_on`
rather than calling the scheduled-scan `CheckZimsecDeadlinesAction`),
`Validation\Index` (folds validation-rule management into the
candidate-errors screen — a rule is data, fixed here; a candidate's
bio-data is never re-keyed, BR-CMP-01-001), `Fees\Index`, `Export\Index`
(folds `RecordZimsecSubmissionAction` in, per the spec's own §1 literal
ordering), `Statements\Index`, `ResultsImport\Index` (raw
comma-separated textarea rows, the same low-volume-input precedent
`Payroll\Statutory\Config` established), `Analysis\Index`. No gap-filling
Actions were needed — all 50 backend Actions across the four CMP
modules pre-existed this pass.

**CMP-02 note 🇿🇼.** Built (`Livewire/Mopse/{SchoolReturns,
InspectionPack}/`, 2 screens — **no §4 screens table exists in the spec
for this module**, this pass's own design): `SchoolReturns\Index` folds
the whole generate → quality-check → export → submit lifecycle onto one
action-bar screen (mirroring `Reports\Close\Checklist`'s precedent);
`InspectionPack\Index` is separate since it produces a bare `File`, not
a `statutory_school_returns` row. Permissions registered under module
code `MOPSE` (`mopse.manage`/`mopse.view`), this pass's own choice in
the absence of a spec-given name.

**CMP-03 note 🇿🇼 ⭐.** Built (`Livewire/Privacy/{ConsentTypes,Consents,
Retention,Disposal,Requests,Breaches,Processing,Processors,Notices}/`,
9 screens, matching the spec's own §4 table exactly). `Retention\Index`
and `Disposal\Index` show ONLY queue/schedule metadata — record class,
table names, retention years, trigger, disposal method, a bare
`record_type`/`record_id` pointer — and never query, render, or even
name in a docblock a safeguarding case's own content, verified by a
dedicated static-scan test (`Cmp03PrivacyAdminUiTest`, mirroring Book
G's own no-delete scan). See `.ai/rules/compliance.md` for the full
reasoning and a real gotcha this pass's own first draft hit (a
docblock literally naming `Modules\Welfare`'s action tripped the scan).

**CMP-04 note.** Built (`Livewire/Policy/{Policies,StatutoryDocuments,
Minutes,IncidentRegister}/`, 4 screens — **no §4 screens table exists
in the spec for this module either**, this pass's own design).
`StatutoryDocuments\Index` folds the contract register in (both tables
share `CheckDocumentExpiryAction`'s one expiry mechanism) and is named
to avoid reading like, though it would not have collided with,
`Core\Livewire\Documents\Index`. `IncidentRegister\Index` excludes
safeguarding entirely (AC-CMP-04-003), verified by an admin-UI test
asserting the only entry present is `data_protection`, never
`safeguarding`.

**A real Livewire gotcha found and fixed in this pass**:
`AnalyseZimsecPassRatesAction`'s `ZimsecPassRateResult` and
`GenerateConsolidatedIncidentRegisterAction`'s
`Collection<ConsolidatedIncidentRegisterEntry>` are both custom
readonly DataObjects — Livewire has no synthesizer for an arbitrary
plain object, so assigning either straight to a public property threw
`Property type not supported in Livewire` the moment either screen's
`->call()` tried to dehydrate. Fixed by converting both to plain arrays
before assignment, in `Zimsec\Analysis\Index` and
`Policy\IncidentRegister\Index` respectively. See `.ai/rules/compliance.md`.

Combined test count for the second pass: **16 new admin-UI tests**
across the four CMP modules (4 CMP-01 + 3 CMP-02 + 5 CMP-03 + 4 CMP-04),
all green — Compliance module total 47 (31 pre-existing backend + 16
new), alongside the whole-app suite.

### Book I — Communication & Portals — ✅ complete

| Module | Screens | Status |
|---|---|---|
| COM-01 | Messaging Gateways & Delivery | ✅ (`Livewire/Messaging/`) |
| COM-02 | Event-Driven Automation Rules | ✅ (`Livewire/Automation/`) |
| COM-03/04/05 | Portal Services (Parent, Learner, Staff) | ✅ admin screen only (`Livewire/Portal/Admin/`) — see note |
| COM-06 | Calendar, Events & Notice Board | ✅ (`Livewire/{Calendar,Notices,Newsletters,Events}/`) |
| COM-07 | Virtual Meetings | ✅ (`Livewire/{Meetings,Consultations}/`) |
| COM-08 | Feedback, Surveys & Complaints | ✅ (`Livewire/{Surveys,Complaints,ExitInterviews}/`) |

**COM-01 note.** Built (`Livewire/Messaging/{Gateways,WhatsApp,Sms,Reports}/`,
6 screens, matching the spec's own §6 table): `Gateways\Index` (register,
health check, activate/deactivate), `Gateways\Webhooks`, `WhatsApp\Templates`
(folds WhatsApp Business account registration and Meta quality rating in
with template submit/review), `Sms\SenderIds`, `Reports\Cost`,
`Reports\Reconciliation`. Permissions registered under module code `COMMS`
(`comms.gateway.manage|view`, `comms.template.manage`,
`comms.sender_id.manage`, `comms.report.view`,
`comms.reconciliation.manage`) — the backend pass had registered none.
Two gap-filling Actions: `RegisterWhatsAppBusinessAccountAction`,
`SetMessageGatewayActiveAction`. Credentials are write-only (never read
back into a view); the webhook log shows receipt metadata only, never
raw payloads. **Deliberately not built:** the spec's gateway "test send"
(needs `CORE-09`'s recipient-resolving dispatch path; a health check
already exercises the driver), and a stored currency for reconciliation
(the table has no currency column, so amounts use the school's base
currency). Small registers use plain tables, not the shared data-table,
like the Compliance pass. Comms module: 79 tests (61 backend + 18 new
admin-UI), all green. PHPStan could not be run in the cloud session
(larastan's `phpstan/phpstan` is distributed only as a GitHub zipball,
blocked by the sandbox proxy) — run `vendor/bin/phpstan analyse` locally
before relying on this module being PHPStan-clean.

**COM-02 note.** Built (`Livewire/Automation/{Index,Builder,ExecutionLog,
ScanRuns,Variants}`, 5 screens, matching the spec's own §5 table).
Permissions registered under module code `AUTOMATION` (`automation.view`,
`automation.manage`) — the spec names them without a `comms.` prefix.
No new Actions were needed. `Builder` is two screens in one: with no rule
it is the condition-group builder (field picker scoped to the chosen
entity/event, saves the rule **inactive**); with a rule it is that rule's
own screen — preview (no dispatch, no `rule_executions` write), cost
estimate, activate/deactivate. The spec's "cost estimate before save" is
delivered as "before activation" (BR-COM-02-008's actual requirement),
because the backend preview/estimate Actions take a saved rule id.
**Deliberately not built:** editing a saved rule's conditions (the backend
has no update Action — deactivate and re-create), removing a variant or
changing its weight (same), previewing an *event* rule (preview scans
current data, which only exists for scheduled-scan rules), and a "run
scan now" button (it would send real messages; the cron wiring for
`RunScanRuleAction` is still the backend's documented deferred gap).
Comms module: 96 tests (61 backend + 35 admin-UI), all green.

**COM-03/04/05 note.** One admin screen, by design:
`Portal\Admin\Widgets` (`Livewire/Portal/Admin/Widgets`) — school-level
enable/reorder of dashboard widgets per persona (parent / learner /
staff). The spec's other COM-03/04/05 surfaces are **not Livewire**: the
parent, learner and staff dashboards, the onboarding wizard and device
registration are consumed by the Next.js and Flutter apps over the
`/api/v1/portal/*` API (this panel is internal staff only), and device
management is `CORE-05`'s own `Core\Profile\Devices`. Those API
endpoints are still unbuilt — see "Known, deliberately-documented backend
gaps" above. Permissions registered under module code `PORTAL`
(`portal.widget.view`, `portal.widget.manage`); the spec's three
`portal.dashboard.view.*` permissions are **not** registered, because
nothing enforces them until the portal API exists. Widgets for a module
the school has not enabled are never listed (a small new
`EnabledWidgetsResolver::availableForConfiguration()` applies the same
entitlement filter `resolve()` does, without the enabled-state filter);
`portal.dashboard_widget_max_per_persona` caps how many a school can
enable. The learner "tell someone" entry point is not a widget, so no
configuration can remove it — the screen says so. Comms module: 104
tests (61 backend + 43 admin-UI), all green.

**COM-06 note.** Built 6 screens, matching the spec's own §4 table:
`Calendar\View`, `Notices\Index`, `Notices\Compose`, `Newsletters\Compose`,
`Events\Register`, `Events\CheckIn`. Permissions are registered under four
module codes (`CALENDAR`, `NOTICES`, `NEWSLETTERS`, `EVENTS` — the spec names
them without a `comms.` prefix, same multi-code pattern as Compliance).
One gap-filling Action: `CreateNewsletterAction` (the backend had a
`newsletters` table and model but no way to create one). The calendar is
filtered to what the *viewing user* may see via `GetCalendarForViewerAction`;
creating a manual event and the on-demand rebuild need `events.manage`
(the spec names no permission for them). Audience pickers offer whole
school, staff, section and grade level only — `class` and `house` are not
offered because `CalendarAudienceFilter` cannot resolve them, so a notice
scoped to one would reach nobody. A full event waitlists rather than
failing, and cancelling promotes the next person. A ticketed event only
supports a *learner* attendee (the backend raises the FIN-02 ad hoc charge
against a student), so the screen asks for an admission number; payment is
confirmed by entering an existing cashier receipt number — the backend's
own documented bridge for the missing charge-to-receipt settlement, i.e.
"paid" is human-asserted. **Deliberately not built:** newsletter *sending*
(no backend sender exists — an issue only ever reaches draft/scheduled),
notice attachments (CORE-10's file picker isn't wired), the iCal feed and
its tokens and ticket scanning (API/mobile surfaces), and read-rate
denominators on the notice board (the backend only approximates the
audience size for narrow scopes). Comms module: 122 tests (61 backend +
61 admin-UI), all green.

**COM-07 note.** Built 5 screens, matching the spec's own §5 table:
`Meetings\Providers`, `Meetings\Index`, `Consultations\Windows`,
`Meetings\AttendanceReview`, `Meetings\Recordings`. Permissions are
registered under module code `MEETINGS` (`meetings.manage|view|
consultation.manage|recording.view`); the attendance screen uses
Academic's existing `academic.attendance.mark`. **`meetings.waiting_room.override`
is this pass's own name** for the "explicit permission" AC-COM-07-005
requires but never names. No backend Action was added. Provider
credentials are write-only; `host_url`/`passcode` are never selected into
the schedule and are shown only to the meeting's own host via
`ResolveMeetingHostCredentialsAction`. A learner-facing meeting always
starts with its waiting room on; turning it off is a separate override —
a user without the permission still triggers the Action so the
unauthorised attempt is logged, then is refused. Attendance review is
advisory: the session and matched learners are re-derived server-side on
every confirm (never trusted from component state), a below-threshold
learner gets no default status, and an unmatched participant is listed
but must be marked from the register itself — there is no backend Action
to match one, and none was invented. **Deliberately not built:** guardian
consultation booking and the join/my-schedule endpoints (portal API),
the webhook secret field (the backend registration Action has no write
path for it), a meeting-webhook log screen (not in the spec's table), and
a purge schedule for recordings (the screen offers on-demand purge; the
cron wiring remains the backend's documented deferred gap). Comms module:
141 tests (61 backend + 80 admin-UI), all green.

**COM-08 note.** Built 7 screens: the spec's six —
`Surveys\Builder`, `Surveys\Results`, `Complaints\Submit`,
`Complaints\Queue`, `Complaints\Show`, `ExitInterviews\Index` — plus
`Complaints\Categories`, **this pass's own addition** (the backend could
raise a complaint but had no way to create the category every complaint
needs, and a category carries the SLA and the safeguarding flag).
Permissions: `surveys.manage|view` and `complaints.manage`; intake needs
none beyond school membership ("any authenticated user"), and `Show` is
also open to the complaint's own assignee. **Four gap-filling Actions**,
because the backend had no way to do these and the SLA check never fires
for an unassigned complaint: `CreateComplaintCategoryAction`,
`AssignComplaintAction`, `ChangeComplaintStatusAction` (refuses a finished
or safeguarding-routed complaint; resolution still goes through
`ResolveComplaintAction`) and `CloseSurveyAction`.

**Safeguarding (BR-COM-08-006).** A complaint routed to BRD-08 is shown in
the queue by number and category only, and its detail page is a stub: its
subject, description, raiser, related learner and thread are never
selected into any view, and no action is offered on it — verified by a
test that greps the rendered output for a sentinel string. The raiser's
own list likewise shows only "referred to safeguarding". The complaints
row still holds a copy of the description (the backend writes it), so that
copy is only protected by this screen not reading it; moving or removing
it is a backend decision.

**Other behaviour.** The raiser's identity is derived server-side (staff or
guardian record, else anonymous); "submit anonymously" stores none. The
raiser's own list and the assignee's "visible to the raiser" thread both
read through `GetComplaintThreadForRaiserAction`, so an internal note never
reaches the raiser. Survey results are aggregates only — counts, a scale
average, NPS, and free text with no respondent. A survey opens as soon as it
is saved and cannot be edited (no update Action), and a skip rule may only
jump forward. **Deliberately not built:** the respondent-facing survey form
and survey distribution, the portal complaint endpoints, the raiser's
satisfaction rating, SLA alerts being *scheduled* (the Action exists; the
cron wiring is the backend's documented deferred gap), and the head's
termly aggregate report (BR-COM-08-008). Comms module: 162 tests (61
backend + 101 admin-UI), all green. **This completes Book I's admin UI.**

**A real Livewire gotcha found in this pass**: component names derive
from the class path under the module's `addLocation()` namespace, so
`Modules\Comms\Livewire\Gateways\Index` and Finance's own
`Gateways\Index` both resolve to the name `gateways.index`; a Livewire
update request then re-hydrated the *Finance* component. See
`.ai/rules/comms.md`.

### Book J — Intelligence & SaaS Control — 🟡 in progress

Build order from the spec: INT-01 → INT-02 → INT-03 → INT-04 → SAA-01 →
SAA-02 / SAA-03. INT screens are **school-facing** (this panel); SAA-01/02/03
are **vendor-facing** and must live in a separate authentication realm, never
in this panel's sidebar, routes or permissions (Book J §0.2).

| Module | Screens | Status |
|---|---|---|
| INT-01 | Reporting Engine & Data Warehouse | ✅ (`Livewire/Insights/Reports/`) |
| INT-02 | Executive Dashboards | ✅ (`Livewire/Executive/`) |
| INT-03 | Early Warning & Predictive Analytics | ✅ (`Livewire/EarlyWarning/`) |
| INT-04 | Public API, Webhooks & Integrations | ✅ admin screens (`Livewire/Integrations/`); 🟡 public REST surface written but **its Pest tests have not been run** (see INT-04 note) |
| SAA-01 | Licensing, Subscription & Entitlement | ✅ admin screens (`Livewire/Tenant/Subscription/`, `Livewire/Vendor/{Subscription,Billing,Licensing}/`); `/api/v1/subscription/*` ⬜ (no such route in `routes/api.php`; correct) |
| SAA-02 | Vendor Control Centre | ✅ admin screens (`Livewire/Vendor/{Tenants,Rollouts,Releases,Broadcasts,Incidents}/`, public `/status`) |
| SAA-03 | Onboarding, Support & Customer Success | ✅ admin screens (`Livewire/Vendor/{Onboarding,Support,Adoption,ChurnRisk}/`, `Livewire/Tenant/Support/`, public `/help`) |

**SAA-03 note.** Vendor screens `Onboarding\Index` (tracker), `Onboarding\Templates`,
`Support\Queue` (SLA-sorted), `Adoption\Index`, `ChurnRisk\Queue`; school-facing
`Tenant\Support\Raise` (`account.support`, permission `support.ticket.raise`) and the
public, read-only help centre at `/help` and `/help/{slug}` (the `GET /api/v1/help/articles`
equivalent; article Markdown is rendered with raw HTML escaped). A ticket's tenant,
school and author are derived on the server (the author must be a user of the tenant, the
school one of the tenant's), and it lands in `support_tickets` — never in the school's
COM-08 complaint queue (AC-SAA-03-001, asserted). **New/hardened Actions:**
`AssignSupportTicketAction` (vendor staff only), `ChangeSupportTicketStatusAction` (a real
state machine; `closed` is final), `ReviewChurnRiskFlagAction`, `CreateOnboardingTemplateAction`
(one library entry per profile); `RaiseSupportTicketAction` and `StartOnboardingChecklistAction`
validate input, tenant/school/author consistency, unique step keys and vendor-only success
managers. Churn flags always render their plain-language, weighted, sourced factors
(AC-SAA-03-005). **Known gaps:** no ticket reply thread / resolution note (the spec's
`support_tickets` has none); the churn flag has no intervention-note column, so
`intervention_logged` records the move only; no vendor authoring UI for knowledge-base
articles, product tours or release-note distribution (Actions exist; the spec's screen
list does not include them); the `/api/v1/support/*`, `/help/articles` and `/tours/*`
endpoints; no scheduled stall alerts, adoption recompute or SLA checks (on-demand buttons
only).

**SAA-02 note.** Vendor screens `Tenants\Index`, `Tenants\Show`, `Rollouts\Index`,
`Releases\Index`, `Broadcasts\Compose`, `Incidents\Manage` (routes `vendor.*`), the
unauthenticated `/status` page (public incidents only, `GetPublicStatusAction`) and a
school-facing `Tenant\Announcements` (`account.announcements`, BR-SAA-02-006 — the
tenant's own all-tenant and explicitly-targeted notices only). Health scores are always
shown beside their component signals (AC-SAA-02-004). Opening a tenant, recomputing
health and every rollout/release/broadcast/incident action are written to the vendor
audit trail (BR-SAA-02-007) and listed on the tenant page. **Backend hardening:**
rollouts need existing pilot/cohort tenants, refuse an already-global flag and a second
concurrent rollout, and bound a percentage stage to 1–99; canary releases validate the
version format, tenants and uniqueness, and a rolled-back release can no longer be
"confirmed stable" or rolled back twice; broadcasts validate severity, length, window and
tenants, and an empty audience is an error rather than "all"; incidents validate severity,
components and status and refuse updates once resolved. **Known gaps:** (1) the
**impersonation entry point** (BR-SAA-02-002) is now built — see the gap-closing pass; (2) `GET /api/v1/vendor/tenants/{id}/health`
is not built; (3) nightly health snapshots are not scheduled (on-demand recompute only);
(4) a tenant's own incident visibility to its administrators is the `Announcements`
page, not yet a global banner.

**SAA-01 note (and the vendor realm).** School-facing `Tenant\Subscription\MySubscription`
(`account.subscription`, permissions `subscription.view`/`subscription.manage`) and
vendor-facing `Vendor\Subscription\Plans`, `Vendor\Subscription\Manage`,
`Vendor\Billing\Invoices`, `Vendor\Licensing\Keys` (`vendor.*`). **Vendor realm design:**
the backend had already settled it — a vendor is `users.user_type = vendor`, gated by the
`serp.vendor` group / `EnsureVendorGuard` (identity, IP allowlist, confirmed 2FA), not a
grantable permission and not a second user table; this pass builds on that rather than
adding a `vendor` auth guard. Because Livewire update requests do not re-run route
middleware, every vendor component re-runs the guard (`AuthorizesVendorConsole`) in mount,
render and each mutation, and every tenant-affecting action is written to the activity log
(`RecordVendorConsoleActionAction`, BR-SAA-02-007). A separate `vendor/` layout; nothing
vendor-facing is linked from the school sidebar. **Backend hardening:** subscription
lifecycle is now a real state machine (`assertMayTransition` — a cancelled subscription can
no longer be "reactivated"); `ChangeSubscriptionPlanAction` refuses same/withdrawn plans and
non-live subscriptions; `CreateSubscriptionAction` refuses a withdrawn plan, schools outside
the tenant (entitlements are written per school) and a second live subscription;
`RecordTenantPaymentAction` refuses zero, wrong-currency and void-invoice payments;
`IssueLicenceKeyAction` binds the key to the subscription's own tenant; new
`SetSubscriptionPlanActiveAction`. **Known gaps:** the `/api/v1/subscription/*` endpoints;
no scheduled renewal/past-due/usage-metering runs (screens call the Actions on demand); no
plan create/edit form (catalogue is read + withdraw/offer); `serp.vendor`'s IP allowlist is
deliberately permissive while `VENDOR_IP_ALLOWLIST` is unset (existing, documented stance) —
**set it in every deployed environment**; all vendor staff currently have equal console
access (the spec defines no vendor sub-roles).

**INT-04 note.** Built the spec's 6 screens — `Clients\Index`, `Webhooks\Index`,
`Webhooks\Log`, `Sso\Index`, `Hardware\Index`, `Usage\Dashboard` — under
`insights.integrations.*`, with permissions `integration.manage` (dangerous),
`integration.view`, `integration.webhook.manage`, `integration.sso.manage`
(dangerous), `integration.hardware.manage`. Keys, signing secrets and SSO
credentials are shown once / write-only. Backend hardening: (1) webhook target
SSRF — `WebhookTargetUrl` (https only, no private/loopback/link-local/reserved
addresses, DNS-resolved addresses checked, no redirects) enforced on create and
on every dispatch; (2) a subscription's client must be an active integration
client of the same school; (3) `IssueApiClientAction` validates name, type,
non-empty abilities, rate limit 1–10,000 and IP/CIDR allowlist; (4) new
`RotateApiClientKeyAction`, `SetWebhookSubscriptionActiveAction` (re-enable resets
failures), `SaveSsoProvisioningConfigAction`; (5) revoking a client switches off
its webhooks. **Public REST surface pass (written, NOT yet test-verified).** Added
`GET /api/v1/openapi.json` (`OpenApiDocumentBuilder`: generated from the router's
`api/v1` routes, ability middleware and any `FormRequest` rules; BR-INT-04-010),
`POST /api/v1/hardware/scan` and `POST /api/v1/hardware/{ulid}/heartbeat`
(`HardwareController`, `HardwareScanRequest`), the `serp.api-client` middleware
(`AuthenticateApiClient`: bearer `{client ulid}.{secret}`, bcrypt-verified; revoked, IP-allowlist
and missing-ability refusals; per-client `rate_limit_per_minute` with 429 + `Retry-After` and the
`RateLimitExceeded` event; one `api_usage_log` row per authenticated call and `last_used_at`),
public `GET /developers`, and the scheduled task `intelligence.retry_webhook_deliveries`
(`RetryFailedWebhookDeliveriesAction`, 2^attempts-minute backoff capped at 6h, via
`DispatchWebhookAction::attempt()`). Pest files: `Int04OpenApiTest`, `Int04HardwareApiTest`,
`Int04DevelopersPageTest`, `Int04WebhookRetryTest`. **Verification status:** the authoring sandbox
could not install composer dependencies (GitHub-hosted dist and source downloads were denied), so
these tests and the existing suite were only `php -l` linted, never executed. Run
`vendor/bin/pest Modules/Intelligence/tests/Feature` before treating BR-INT-04-003/010 and
AC-INT-04-001/005 as met. Choices where the spec is ambiguous: (1) API keys are now
`{client ulid}.{secret}` (previously a bare random string) so the row can be found before the hash is
checked; keys issued earlier cannot authenticate and must be rotated; (2) the scan body adds
`target_id` (roll call or checkpoint id) and optional `direction`, which the spec's example omits but
`RecordHardwareScanAction` needs; scans are attributed to the user who registered the device
(`api_clients.created_by`); (3) a device may only call as itself (`device_id` must belong to the
calling key); (4) rate limiting is a per-client one-minute window via the cache limiter, applied to
`serp.api-client` routes only; (5) `/developers` is a read-only page generated from the OpenAPI
document, not hand-written prose; (6) the usage dashboard needs no change but is untested against live
rows. **Still open:** `INT-04` ability allow-list still has only `usage:read` and `{purpose}:write`
(no payroll or other third-party REST endpoints exist, so AC-INT-04-001 is only exercised against the
hardware routes); `attendance` (`ACA-04`) is still not a registered hardware route; webhook dispatch is
not yet hooked to domain events (`TriggerWebhooksForEventAction` has no listeners); the vendor-side
aggregate of usage (BR-INT-04-011) is not built; the manual "flag silent devices" button remains
alongside `intelligence.mark_offline_hardware`.
`ProvisionSsoStaffAccountAction`
takes a free-text role name and is deliberately not exposed on any screen until
an IdP sync exists to drive it and the role is restricted.

**INT-03 note.** Built the spec's 6 screens — `EarlyWarning\Queue`,
`StudentDetail`, `FeeRisk`, `Enrolment`, `StaffWellbeing`, `Weights` — under
`insights.early-warning.*`. New permissions: `risk.review` (dangerous),
`risk.configure`, `staff.wellbeing.view`. Scores are advisory: no screen
contacts a guardian, none is reachable without a staff permission
(AC-INT-03-002), and flag closure needs a note (AC-INT-03-004). Spec
deviations and backend hardening: (1) the spec's `finance.report.view` was
never registered by Finance — `FeeRisk` accepts `finance.report.debtors` or
`finance.report.collections`; (2) `SetRiskScoreWeightAction` is new — weights
are bounded 0–100 (the spec names "registered bounds" but no figures) and only
registered learner indicators can be re-weighted or disabled; (3)
`ReviewWithdrawalRiskFlagAction` now rejects unknown statuses and re-review of
a closed flag; (4) `ComputeFeeDefaultRiskAction` clears rows for households
no longer overdue (the table is one current row per guardian);
(5) `StaffWellbeingVisibility` limits wellbeing to the staff member and their
`reports_to_staff_id` line manager — no school-wide override. **Known gap:** no
nightly recompute is scheduled (BR-INT-03-005, `risk.recompute_hour`) — the
scheduled-task sync gap in `.ai/rules/commands.md` blocks it; recompute is
on demand from the screens until that platform fix lands. Likewise the three
`/api/v1/risk/*` endpoints are not built (API surface is INT-04's pass).

**INT-02 note.** Built the spec's 4 screens — `Executive\HeadDashboard`,
`Executive\BursarDashboard`, `Executive\Kpis`, `Executive\BoardPack` — under
`Livewire/Executive/`. Permissions under module code `EXECUTIVE`
(`executive.dashboard.view`, `.view.finance`, `executive.kpi.manage`,
`executive.board_pack.generate`); the head and bursar dashboards are on
separate permissions. KPI tiles are colour-coded from `GetKpiValueAction`
(AC-INT-02-001) and link to the *owning module's own* report where one exists
(only `finance.reports.collections` / `aged-debtors` today — a KPI with no such
screen has no link rather than a parallel detail view). The head's page also
shows the three-year enrolment comparative read from warehouse snapshots, the
open stock-consumption anomaly count (FIN-09's data, only surfaced), the
executive widgets resolved through COM-03's registry, and a "send me today's
digest" action (exceptions only, through CORE-09). The warning threshold is
compared **directly** with the KPI's own value (the backend's reading of the
spec's worked example), so for a non-percentage KPI such as days overdue it must
be set deliberately — the KPI screen says so. The board pack offers only the
four sections the backend can resolve (enrolment, financial = FIN-12's income
statement unmodified, staffing, boarding); `academic` and the INT-03 risk
summary are not offered. One backend hardening: `SetKpiTargetAction` now refuses
an unregistered KPI key. **Deliberately not built:** the `/api/v1/executive/*`
endpoints, the daily digest *scheduling* (the Action exists; the cron wiring is
the backend's deferred gap), a manual warehouse-snapshot trigger (the
comparative shows "no snapshot yet" until the nightly rebuild runs, and nothing
schedules it yet), the fuel-anomaly list (no persisted anomaly table exists),
and a PDF board pack (it is a JSON file in the vault). Intelligence module: 79
tests; PHPStan clean.

**INT-01 note.** Built the spec's 5 screens — `Reports\Builder`, `Reports\Index`
(My reports), `Reports\Shared`, `Reports\Schedule`, `Reports\ExecutionLog` —
under `Livewire/Insights/Reports/` (the name `reports.*` is already taken by
Farm/Finance/Payroll/Fiscal). Permissions registered under module code
`REPORT` (`report.build|schedule|view_audit` and `report.sensitive_field.access`,
which the backend checked by name but never registered).

**Three real security gaps in the backend were found and fixed before exposing
the builder** (each has a regression test, `Int01QueryHardeningTest`): (1) a
*filter or group-by* on a field the runner cannot read was applied unchecked —
`WHERE basic_salary_minor > N` over a report that only selects a name is an
inference oracle, contradicting AC-INT-01-001 — so every filter/group field is
now registered, filterable/groupable and readable by the running user; (2) the
column alias was concatenated into raw SQL — now a plain identifier only; (3)
`RunSavedReportAction` ran any report for anyone who knew its id — now only the
author or a share recipient (user or role). Field permission checks go through
a new `ReportFieldAccess`, which treats a never-created permission as "not
permitted" (spatie otherwise throws and the builder 500s). One existing test
("re-evaluates a **shared** report…") never actually shared the report and only
passed because of gap (3); it now shares it.

**Deliberately not built:** the chart preview (the chart type is stored for a
later renderer), report edit/delete and schedule pause/delete (no backend
Action), PDF/Excel/CSV export and the `/api/v1/reports/*` endpoints, and
attached files on scheduled delivery (the recipient is notified a report is
ready; no file is rendered, and the cron wiring is still the backend's deferred
gap). The warehouse is row-count tracking only, as its own Action documents.
Intelligence module: 63 tests, all green; PHPStan clean.

### Book K — Closing the Catalogue — ✅ admin UI complete
FIN-07 (lives in `Modules/Finance`, alongside FIN-01–06), PPL-06 (lives in
`Modules/People`, alongside PPL-01–04), ACA-08–ACA-11 (lives in
`Modules/Academic`, alongside ACA-01–07). Backends were already built.

| Module | Admin UI |
|---|---|
| FIN-07 | ✅ `Finance/Livewire/{Discounts,Scholarships}/`, `Reports/Discounts` |
| PPL-06 | ✅ `People/Livewire/Alumni/` |
| ACA-08 | ✅ `Academic/Livewire/Lms/` |
| ACA-09 | ✅ `Academic/Livewire/Cbt/` (staff side) |
| ACA-10 | ✅ `Academic/Livewire/Library/` |
| ACA-11 | ✅ `Academic/Livewire/Supervision/` |

**ACA-11 note.** Seven screens under `academic.supervision.*` — `Supervision\SchemeOfWork`,
`LessonPlans`, `Coverage`, `Observe`, `ObservationHistory`, `Meetings`, `TeacherDashboard` — and the
`supervision.*` permissions (`plan`, `view`, `scheme.approve`, `observe`, `rubric.manage`,
`meeting.manage`; `plan` and `rubric.manage` are additions to the spec's list for teacher
self-service and rubric authoring). Reach is resolved server-side from the signed-in user's own
staff record: a teacher sees and acts on their own schemes, plans, coverage and observations; a
head of department (`Department.head_staff_id`) with `supervision.view` sees their department;
`supervision.view` at school reach sees everyone; the observer is always the signed-in user.
**Backend fixes:** scheme, lesson-plan, rubric, observation and meeting Actions now validate
ownership and shape (teacher, term, subject, grade level belong to the school; one scheme per
teacher/subject/grade/term; a plan can only link to the teacher's own scheme; observations score
every rubric criterion with one of its defined levels, cannot be of oneself, and a follow-up must be
of the same teacher and later; minutes need attendees and action items need an owner and due date);
a teacher can no longer approve their own scheme or review their own plan; a scheme can only be
returned with a reason; delivery dates must fall between term start and today; new
`UpdateSchemeOfWorkAction` (revise a draft or returned scheme, keeping coverage records in step).
**Known gaps:** lesson plans cannot be linked to a timetable slot from the UI (the Action supports
it); the dashboard omits ACA-04 marking-compliance and ACA-05 results outcomes (spec "where
enabled"); no feed into PPL-04 appraisal yet (BR-ACA-11-007); `/api/v1/supervision/*` endpoints;
schemes are written for the current term only.

**Book K acceptance gate.** Not audited checkbox by checkbox in this admin-UI pass. The backend
tests written with each module cover the rules behind most items (discount resolver and budget
refusal, CBT timer and resume, bulk-issue exceptions, ad hoc fine charging, the observer/observed
boundary, alumni snapshot); coverage percentages were not measured and the "every business rule
has a named test" item was not checked rule by rule. Do both before calling the gate green.

**ACA-10 note.** Six screens under `academic.library.*` — `Library\Catalogue`, `Circulation`,
`BulkIssue`, `Overdue`, `StockTake`, `Acquisitions` — and the `library.*` permissions (`view`,
`catalogue.manage`, `circulate`, `bulk_issue`, `stocktake`, `acquisition.request`,
`acquisition.approve`; the last two beyond the spec's list, to separate cataloguing and
approving from the desk). **Backend fixes:** the loan, copy, item, category and acquisition
Actions now validate input and ownership (borrower and term belong to the school, retired
titles cannot be lent, ISBN/barcode duplicates refused, copy locked at issue); a late return
by a learner now refuses without a fee component instead of silently dropping the fine; a
lost copy of a title with no replacement cost is refused for a learner; staff borrowers are
never charged to a fee account; bulk issue no longer double-issues a title a learner already
holds and bulk return checks class, term, titles and fee component; only one stock-take may be
in progress; scans are limited to this school's copies. New `ApproveAcquisitionRequestAction`
raises a FIN-08 purchase requisition linked by `source_type`/`source_id`
(BR-ACA-10-010). **Known gaps:** no reservation queue, so renewal ignores reservations
(BR-ACA-10-003); no scheduled escalating overdue reminders (on-demand button only,
BR-ACA-10-004); no "raise the limit with a reason" override; bulk issue uses the `secondary`
category and a four-month period; `/api/v1/library/*` endpoints; barcode/QR label printing.

**ACA-09 note.** Five staff screens under `academic.cbt.*` — `Cbt\Bank`, `Builder`,
`Monitor`, `ManualMarking`, `ItemAnalysis` — and the `cbt.*` permissions (`bank.manage`,
`test.manage`, `test.monitor`, `mark`). The candidate **Delivery** screen is deliberately not
in the staff panel: test-taking belongs to the learner app against `/api/v1/cbt/*`, and no
staff screen answers on a candidate's behalf. **Backend fixes** (the spec's headline rules
were not actually enforced): `SaveResponseAction` accepted answers after time had run out
and for questions not on the test — it now refuses both (BR-ACA-09-002, AC-ACA-09-006);
`SubmitAttemptAction` marks any past-time submission auto-submitted; `CloseCbtTestAction`
left in-flight attempts dangling — it now submits them with what they saved; new
`AutoSubmitExpiredAttemptsAction` for attempts nobody came back to;
`RecordFocusEventAction` now ignores events on unmonitored or finished attempts, validates the
event type and applies `cbt.default_max_tab_switches` when a test sets no limit; question-bank
and test creation validate type/difficulty, the correct answer, that auto-marking follows the
type, chosen questions belonging to this subject and school, dates and duration; new
`SetQuestionActiveAction`. **Known gaps:** matching items cannot be authored in the UI;
fill-in answers match exactly (case-sensitive); the `flagged` status is lost when an attempt
is submitted, so the monitor derives the review flag from the tab-switch count; no
test-editing after scheduling (build a new test, as the Action docblock says); no
scheduled sweep for expired attempts (on-demand button); the `/api/v1/cbt/*` endpoints.

**ACA-08 note.** Six teacher/administrator screens under `academic.lms.*` —
`Lms\CourseSpaces`, `CourseSpace`, `AssignmentCreate`, `Marking`, `NonSubmission`,
`Discussion` — and the `lms.*` permissions (`course.manage`, `assignment.create`,
`assignment.mark`, `discussion.moderate`). Reach is enforced per space: a teacher manages only
the spaces they teach, school-reach holders all (`AuthorizesCourseSpace`). Content shows its
size before download with large and stream-only flags (AC-ACA-08-001); late penalty is fixed
at marking (AC-ACA-08-002); similarity only flags (AC-ACA-08-003); a hidden post is kept
with its moderator and reason. **Backend hardening:** content links must be http(s) and a
file's size now comes from the stored file, an infected file is refused and an unscanned one
cannot be published; assignment, discussion-thread and discussion-post inputs are validated;
`SubmitAssignmentAction` enforces the declared submission type, the opening time and a
valid link; `MarkAssignmentSubmissionAction` refuses unsubmitted work;
`ChaseNonSubmittersAction` reminded any student id it was given — it now reminds only
genuine non-submitters; discussion authorship is checked against the space; course-space
teacher override must be this school's staff. New: `CloseAssignmentAction`,
`SetDiscussionThreadStateAction`. **Known gaps:** the learner-facing surface (`/api/v1/lms/*`,
offline submission queue, learner view of content/assignments/discussion) belongs to the
API/Next.js/Flutter phase; file upload (content comes from the existing file vault or a
link); rubric attachment; no content reorder/edit/archive; the 'both' submission type
accepts any one of file, text or link.

**PPL-06 note.** Seven screens under `alumni.*` (`Directory\Index`, `Directory\Show`,
`Events\Index`, `Campaigns\Index`, `Pledges\Index`, `Donations\Record`,
`Endowments\Index`) and the `alumni.*` permissions (view, career.verify, contact.manage,
event/campaign/pledge manage, donation.record ⚠, endowment.manage ⚠). The frozen
academic summary is shown exactly as stored (AC-PPL-06-002); career updates show as
unverified until confirmed (BR-PPL-06-004); staff can record an opt-out but no screen
offers opting an alumnus back in (BR-PPL-06-011); campaign progress is the money
received, with pledges shown separately (AC-PPL-06-003). **Backend fixes:**
`OfferAlumniPortalAccountAction` set the new user's `tenant_id` to the *school id* — now the
school's tenant, and a duplicate email is refused; `RecordDonationAction` accepted any
account, campaign, pledge or endowment id, any currency and a zero amount — it now
validates ownership, status, currency, term/year, future dates and restricted-gift
purpose, and a donation to a campaign pledge counts toward that campaign automatically;
campaign, pledge, endowment, event and career-update Actions validate their inputs.
**Known gaps:** no way to add a pre-system external alumnus (a gap the backend already
documents); opt-out can only suppress channels for alumni with a portal user, as the
record holds no standalone email/phone; the `/api/v1/alumni/*` and
`/campaigns/{ulid}/progress` endpoints; no donor-recognition roll or reunion
group-coordinator screens; alumni-event tickets/RSVP are COM-06's and not surfaced here;
a lapsed-pledge sweep is not scheduled.

**FIN-07 note.** Nine screens: `Discounts\Schemes`, `Budgets`, `AwardList` (the spec's
`Awards\Index`), `GrantAward`, `ConditionReview`, `SponsorAwards`, `Scholarships\Applications`,
`Committee`, and `Reports\Discounts` (cost of generosity), routes `finance.discounts.*`,
`finance.scholarships.*`, `finance.awards.*`, `finance.reports.discounts`. The ten FIN-07
permissions were never registered — now are (`finance.discount_scheme.*`,
`finance.scholarship.*`, `finance.award.*`, `finance.report.discounts`; decide/grant/revoke are
dangerous). **Backend gaps closed:** the grant-time envelope check the spec requires
(BR-FIN-07-009/AC-FIN-07-003) did not exist — only a billing-time block — so
`GrantAwardAction` now refuses an over-envelope fixed award naming the shortfall
(`PreviewAwardEnvelopeAction` shows it live); input validation on scheme, envelope,
application, decision and grant (scheme/student/year/guardian ownership, percent and amount
bounds, sponsor-iff-sponsor-funded, duplicate awards and applications, contra account must be
the school's own postable account, an automatic scheme must be sibling/staff); decided
applications are final and need a rationale (rejection needs a reason); revoke/condition
review refuse inapplicable statuses; new `SetDiscountSchemeActiveAction` and
`ReinstateAwardAction` (a suspended award's other outcome, with a reason). **Known gaps:**
percentage awards can only be bounded at billing time (their cost is unknown at grant); the
guardian-facing `/api/v1/scholarships/*` and `/students/{ulid}/awards` endpoints; supporting
documents are shown as a count (opened from the file vault); no means-assessment scoring
rubric (the score is entered); condition reviews are on-demand (the
`finance.condition_review_trigger` auto-run on results publication is not wired).

---

## Test & static-analysis status (re-verified on a clean Linux checkout)

Run on PHP 8.4 / Linux with `composer install`, `npm ci` and a built
frontend: **`vendor/bin/pest` — 1758 tests, 0 failed**; **`vendor/bin/phpstan
analyse` — 0 errors** (the project's configured level is **7**, not the 8
the CLAUDE.md tech-stack table says). `NumberingConcurrencyTest` (the Book A
numbering row-lock gate proof) *skips itself* unless a MySQL server is
reachable at `127.0.0.1:3306` as `root` with the password the test hard-codes
(SQLite cannot prove row-level locking); with MySQL 8.0 running it passes, so
a clean environment needs MySQL for that gate to be genuinely verified, not
merely skipped.

Three real portability bugs surfaced only on Linux, and are fixed:
- `Modules/Core` had no `autoload-dev` mapping, so its `tests/Fixtures`
  classes (namespace `Modules\Core\Tests\Fixtures`) only autoloaded on
  case-insensitive filesystems — 47 tests failed on Linux.
- `GenerateTermWeeksAction` took its week boundaries from Carbon's
  *locale* default (`en` = Monday, `en_US` = Sunday) instead of the spec's
  `academic.week_starts_on` setting (default `monday`, CORE-03 §10). The
  setting is now registered (with a Core sync migration for deployed
  databases) and the Action honours it explicitly.
- Two Comms screens tripped PHPStan (a mis-inferred nullsafe and aliased
  `selectRaw` columns read as model properties); both restructured.

Build note: `npm run build` fetches the "Public Sans" font from
`fonts.bunny.net` at build time, so a build environment must allow that
host (or the font config must be vendored locally).

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
