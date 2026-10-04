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
**Deliberately not built**: Learner attendance heatmap, Class
attendance report, Absence follow-up, Statutory register export (no
export-generation Action exists). See `.ai/rules/academic.md`.

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
simplified greedy pass, not a queued annealing job), `Editor` (a plain
add-one-slot form, not drag-and-drop — `CreateTimetableSlotAction`'s
real four-level clash check still runs server-side and refuses with
the conflict named), `Clashes` (runs the real `TimetableClashDetector`),
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
**Deliberately not built**: the spec's own drag-and-drop grid with a
live-updating clash panel, queued generation with a cancellable
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
