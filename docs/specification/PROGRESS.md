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
(`NullStoreIssuanceProvider`). **Deliberately not built**: Cost
analytics/Wastage report (no real costing data exists while `FIN-09`
is unbuilt — building a trend screen over `null` values would be
decorative), `PublicMenu` (a learner/guardian portal screen, not an
admin console concern, matching ACA-06's own precedent for deferring
portal-facing screens), and a dedicated meal-attendance capture UI
(`catering.meal_attendance_capture` defaults off; the default path —
`CloseMealServiceAction`'s own `actual_served` field — is what
`ServicePlan` already uses).

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
per-learner clinical field), `Screenings`. **Deliberately not built**:
a `Consultations` screen — no Action anywhere creates a `Consultation`
row (verified: the model/migration/factory exist, but zero Actions
reference `Consultation::create`), the same "model exists, no Action
ever wrote one" gap prior books hit for other tables; documented rather
than patched with a new Action, since clinical consultation capture
wasn't asked for and the screen list was already large. See
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
(AC-FIN-08-001). **Deliberately not built**: a Contracts screen — no
`CreateSupplierContractAction` exists anywhere in the domain layer
(verified by grep; the model/migration/factory exist, but only
`StoresServiceProvider`'s own tenancy-isolation-test factory call ever
creates a row), the same "model exists, no Action ever wrote one" gap
prior books have hit for other tables.

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
AC-FIN-10-005). **Deliberately not built**: no automatic
capitalisation listener for `FIN-08`'s `CapitalPurchaseReceived`/
`FIN-09`'s `ItemCapitalisationDue` — both events fire for real but
have no subscriber yet, a documented backend gap
(`StoresServiceProvider`'s own docblock, not this pass's to fabricate
an `asset_category_id` column unreviewed).

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
event chain, not a simulated call (AC-FIN-11-001). **Deliberately not
built**: the real enrolment-/collection-rate-driven forecast
projection math — `CreateForecastAction` stores a caller-supplied
`projections` payload as a labelled scenario, matching its own
documented scope boundary (BR-FIN-11-013/014's real `FIN-02`
integration is not built).

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
requires `approved`/`in_progress`). **Deliberately not built**: a
standalone "Contractor management" screen (no `is_contractor` flag or
Action distinguishes a contractor `Supplier` from any other — `WorkOrders\Index`
lets the user pick any supplier for `contractor_supplier_id`) and capital
project milestones (`capital_project_milestones` has the same
"model exists, no Action ever wrote one" gap, but no acceptance criterion
in this book names milestone-level behaviour to build a UI against).

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

### Book H3 — Payroll, Fiscalisation & Compliance — 🟡 in progress (PPL-05/FIN-13/FIN-14/FIN-12 done; CMP-01–04 not started)
Verified module ownership: PPL-05 → `Payroll`, FIN-12 → `Reporting`, FIN-13
→ `Fiscal`, FIN-14 → `Wallet`, CMP-01–CMP-04 → `Compliance`. This pass
covers the four FIN/PPL modules, in the book's own build order
(PPL-05 → FIN-13 → FIN-14 → FIN-12, §0.3 — "largest; independent of
the rest" first, reporting last since it "needs everything else
posting correctly"). **CMP-01–04 (`Modules/Compliance`) are a
separate, not-yet-started pass within this same book** — nothing in
`Modules/Compliance` was touched.

| Module | Screens | Status |
|---|---|---|
| PPL-05 | Payroll & Statutory Deductions 🇿🇼 | 🟡 partial (see note) |
| FIN-13 | ZIMRA Fiscalisation (FDMS) 🇿🇼 | 🟡 partial (see note) |
| FIN-14 | Student Wallet & Tuckshop | ✅ |
| FIN-12 | Financial Reporting & Period Close ⭐ | 🟡 partial (see note) |
| CMP-01–04 | ZIMSEC/MoPSE/Data Protection/Policy Register | ⬜ not started |

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
**A real, documented backend gap found but NOT closed this pass**:
no Action anywhere ever writes a `fiscal_audit_log` row — closing it
needs wiring a log write into six existing call sites, broader than
this book's narrow create-only gap-filling mandate. See
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
the spec's own BalanceSheet/CashFlow/Departmental/Collection/
PriorPeriod/Board have none, verified by grep, and are not built):
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

Combined test count for this pass: **12 new admin-UI tests** across
the four modules (3 Payroll + 3 Fiscal + 3 Wallet + 3 Reporting), all
green, alongside the whole-app suite.

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
