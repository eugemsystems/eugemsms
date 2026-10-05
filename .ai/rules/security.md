---
paths:
  - 'Modules/Security/**'
---

# Security

Book H2 `OPS-06` — Security, Gate & Access Control. Admin-UI pass
built third in this sub-pass's own order (OPS-04 → OPS-05 → OPS-06 →
OPS-07, independent modules per the book's own build order).

## What was built (7 screens, `Livewire/{Muster,OccurrenceBook,Patrols,Contractors,Keys,LostProperty,Drills}/`)

`Muster\Index` ⭐⭐ (the single most operationally important screen in
this book — trigger a drill, live roster from `AssembleMusterRollAction`,
tap-to-mark present, complete the muster), `OccurrenceBook\Index`
(append-only, gapless numbering, no edit/delete control anywhere on
the screen), `Patrols\Index` (routes, schedule, checkpoint scans, a
"check missed patrols" button), `Contractors\Index` ⭐ (folds the gate
sign-in/out hard-refusal flow into the contractor/worker management
screen — see below), `Keys\Index` (uses this pass's own gap-filling
`CreateKeyAndCardAction` — see below), `LostProperty\Index`,
`Drills\Index` (the historical trend/review side of a drill — uses
this pass's own gap-filling `RecordDrillFindingsAction`).

## ⭐⭐ `Contractors\Index`'s gate sign-in is the OPS-06 equivalent of `Boarding\Gate\Terminal` — same no-override discipline, checked for and applied deliberately

Before building this screen, this pass checked whether OPS-06 had
anything structurally similar to `Boarding\Gate\Terminal` (Book F
BRD-03) or `Welfare\Safeguarding\CaseDetail` (Book G) — a hard-refusal,
no-override, safety-critical screen. It does:
`SignInContractorWorkerAction` throws `GateAccessRefusedException`
with `CheckGateAccessAction`'s own specific reason (no police
clearance on file, or the contractor itself isn't currently approved
with current insurance/induction — `BR-OPS-06-001`/`002`) and has **no
override parameter at all**. `Contractors\Index::signIn()` mirrors
that exactly: no override control exists in the UI because none
exists in the Action to call. The result renders as one word
(`ALLOWED`/`REFUSED`) in a large, colour-coded block, the same large
-print, unambiguous-at-a-glance design `Gate\Terminal`'s own class
docblock calls out as "the failure mode this design guards against."

## Two gap-filling Actions were needed in this module

- `CreateKeyAndCardAction` (+ `CreateKeyAndCardData`) — `keys_and_cards`
  had `IssueKeyAction`/`ReturnKeyAction` for moving an *existing* key
  through its lifecycle, but no Action anywhere in the shipped domain
  layer ever created the key record itself (verified by grep across
  `Modules\Security\Domain\Actions`). Always creates `status =
  available`; `IssueKeyAction`'s own master-key gate
  (`MasterKeyRequiresAuthorityException`) applies unchanged from the
  first issue onward.
- `RecordDrillFindingsAction` (+ `RecordDrillFindingsData`) —
  `emergency_drills.findings`/`.actions_required` had no Action
  writing to them: `TriggerEmergencyDrillAction` sets headcount and
  `CompleteMusterAction` sets mustered/unaccounted counts, but neither
  touches the free-text review fields `BR-OPS-06-010` ("trended term
  on term") implies a drill accumulates afterwards.

## `Muster\Index` resolves person names itself — there is no name on a `MusterRosterEntry`

`AssembleMusterRollAction` returns `category`/`personType`/`personId`/
`needsAssistance` only, by design (it's a live cross-module
aggregation, not a denormalised cache). The screen resolves a display
label per `personType` (`Student::find()->fullName()`,
`Staff::find()->fullName()`, `ContractorSiteVisit::find()->contractorWorker->full_name`),
falling back to `"{$personType} #{$personId}"` for a visitor log entry
rather than joining deeper into `Modules\Boarding`'s own visitor
model — sufficient for an operational muster screen, not a guardian
-facing directory.

## Permissions are registered under ONE module code, `SECURITY`

`muster` ⭐ (dangerous — triggers a real drill and opens real `BRD-02`
incidents on completion), `occurrence.record`, `patrol.manage`,
`contractor.manage` (dangerous — includes live gate access decisions),
`key.manage` (dangerous — includes master-key issue), `manage` (lost
property), `drill.manage`.

## Admin-UI test file

`Modules/Security/tests/Feature/Admin/SecurityAdminUiTest.php`, own
`securityAdminFixture()`/`securityAdminUser()` pair. Three tests: a
permission-refusal test, a "renders every screen" smoke test, and a
dedicated AC-OPS-06-004 test (a contractor worker with an approved,
currently-insured/inducted contractor but no police clearance on file
is refused gate access, with the refusal reason naming "police
clearance" verbatim, and creates no `ContractorSiteVisit` row at all).
