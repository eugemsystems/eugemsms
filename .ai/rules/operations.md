---
paths:
  - 'Modules/Operations/**'
---

# Operations

Book H2 `OPS-02` — Maintenance & Works Management. Admin-UI pass built
first in this book's own order (§0.2: "built first because `BRD-01`
damages, `OPS-01` vehicle servicing, `OPS-03` farm equipment and `OPS-04`
generator maintenance all raise work orders against it").

## What was built (7 screens, `Livewire/Maintenance/{Assets,Reports,Schedules,WorkOrders}/` + `Livewire/Maintenance/{Report,Triage}.php` + `Livewire/Projects/`)

`Maintenance\Report`, `Maintenance\Triage`, `Maintenance\WorkOrders\Index`
(folds the spec's separate "Technician job card" screen into this one's
own per-order action bar — approve/complete/verify/issue parts/record
labour/record contractor cost all operate on the selected work order),
`Maintenance\Assets\Index` (also stands in for "Asset maintenance
history"), `Maintenance\Schedules\Index`, `Maintenance\Reports\Index`
(folds the spec's separate SLA and Cost analysis screens), `Projects\Index`.

## `Maintenance\Report` deliberately has no permission check of its own

The spec's own §7 screen-permission table names this screen's permission
literally "any user" — matching BR-OPS-02-001's "requires a location, a
description and a severity, and nothing else." No permission is
registered for it and `Report::mount()` calls only `loadSchool()`/
`loadSessionContext()`, never `authorizePermission()`. This is the one
deliberate exception to the project's own "every screen with a
registered permission calls authorizePermission()" standard — because no
permission exists to check here, not an oversight.

## Three new gap-filling Actions

- `CreateMaintenanceAssetAction` / `CreateMaintenanceScheduleAction` —
  the familiar "model/migration/factory exist, no Action ever created a
  row" gap this project has hit in every book since D (`CreateHostelWingAction`,
  `CreateCurriculumFrameworkAction`, etc.) — confirmed by grep: every
  `maintenance_assets`/`maintenance_schedules` row in the existing test
  suite came from the factory directly, via `TenantModelRegistry`.
- `AdvanceCapitalProjectStatusAction` — `CreateCapitalProjectAction`
  leaves a project `planning`; `CompleteCapitalProjectAction` only
  accepts `approved`/`in_progress`. Nothing in the shipped domain layer
  ever moved a project between those two states. Forward-only, one step
  at a time (`planning → approved → in_progress`), the same shape
  `Modules\Academic\Domain\Actions\AdvanceExaminationSessionStatusAction`
  already established for an analogous gap in Book E.

## Deliberately not built

**Correction (2026-10-07):** all three items below were closed by later gap-closing passes.
`Projects\Index` now has a Details panel to name the main contractor
(`AssignCapitalProjectContractorAction`, active suppliers only) and to add and complete
milestones (`AddCapitalProjectMilestoneAction`, `CompleteCapitalProjectMilestoneAction`,
confirmed present in `Modules/Operations/Domain/Actions/`). "Contractor management" (this
session) now has its missing classification: `suppliers.is_contractor` (new column, Book H1
`Modules\Stores`), `SetSupplierContractorStatusAction` (toggle, mirroring `BlacklistSupplierAction`'s
own single-purpose-status-change shape), and `Ops\Maintenance\Contractors` — flag/unflag any
supplier, see each flagged contractor's real cost/SLA history read straight off
`work_orders.contractor_supplier_id`/`contractor_cost_minor`/`sla_met`, the same "group in PHP over
an eager-loaded collection" discipline `Maintenance\Reports\Index` already established.
`WorkOrders\Index`'s own contractor picker is unaffected — it still lets a work order name any
`Supplier` as `contractor_supplier_id` regardless of this flag, which only governs who shows up on
the dedicated management screen.

**Capital project milestones** (`capital_project_milestones`) — now built, see the
correction above.

## Permissions are registered under ONE module code, `MAINTENANCE`

The spec's own §7 table already names every permission under a single
`maintenance.*` namespace — `view`/`manage`/`triage`/`execute`/
`project.manage`/`report.view`. One `PermissionRegistry::register('MAINTENANCE', [...])`
call.

## `WorkOrder` lifecycle has no explicit "start" transition

`CreateWorkOrderAction` leaves an order `pending_approval` or `approved`
directly; `CompleteWorkOrderAction` accepts either `approved` OR
`in_progress`. No Action anywhere sets `in_progress` — the domain layer
simply never modelled a separate "start work" step. `WorkOrders\Index`'s
own action bar reflects this: there is a "Complete" button for an
`approved` order, no "Start" button, matching the real state machine
rather than inventing a UI step the backend has nowhere to record.

## Admin-UI test file

`Modules/Operations/tests/Feature/Admin/OperationsAdminUiTest.php`, own
`operationsAdminFixture()`/`operationsAdminUser()` pair. Four tests: a
permission-refusal test, a "renders every screen" smoke test, a
dedicated AC-OPS-02-001 test (`Maintenance\Triage`'s own `render()` sorts
`affects_safety` first regardless of reporting order or stated
severity), and a dedicated AC-OPS-02-003 test (`Schedules\Index`'s
"Generate due" button runs the real `GeneratePreventiveWorkOrdersAction`
and produces a genuine `WorkOrder` once a calendar schedule's due date
arrives).
