---
paths:
  - 'Modules/Farm/**'
---

# Farm

Book H2 `OPS-03` — Estates, Farm & Production Units 🇿🇼. Admin-UI pass
built last in this book's own order (after `OPS-02`/`OPS-01`; `OPS-04`
Utilities isn't built yet in this codebase, so the `fields.water_source_id`
FK stays unexercised from this side).

## What was built (10 screens, `Livewire/{Units,Fields,Cycles,Harvest,Livestock,LivestockEvents,Production,KitchenTransfers,Sales,Reports}/`)

`Units\Index`, `Fields\Index`, `Cycles\Index` (folds the spec's separate
"Input recording" screen into the selected cycle's own action bar
alongside labour allocation/overhead apportionment/cycle failure),
`Harvest\Index` (shows cost per kg — the internal transfer price —
immediately after recording), `Livestock\Index` (capitalisation fields
only shown for `purpose = breeding`, mirroring `CreateLivestockAction`'s
own `shouldCapitalize()` gate rather than always offering them),
`LivestockEvents\Index`, `Production\Index`, `KitchenTransfers\Index` ⭐
(see the naming note below — NOT the spec's own bare `Transfers`),
`Sales\Index`, `Reports\Index` (folds the spec's separate "Profitability"
⭐ and "Savings report" screens into one tabbed read).

## ⭐⭐ A real cross-module Livewire component-name collision was found and fixed here — read this before naming any new module-root component

`Modules\Farm\Livewire\Transfers\Index` (this screen's name per the
spec) and `Modules\Stores\Livewire\Transfers\Index` (Book H1 `FIN-09`'s
own inter-store transfer screen, already shipped) are both exactly
`Transfers/Index.php`, one level directly under their own module's
`Livewire/` root. Livewire's full-page component Finder resolves a
route's component by a name derived from the class's path **relative to
whichever registered `Livewire::addLocation()` root matches** — not the
fully-qualified class. Two different modules each registering their own
`Transfers/Index.php` therefore produce the *same* short component name,
and the module whose service provider boots later silently wins **for
both routes**. In this codebase that meant a user granted only
`farm.transfer` got a plain `403` on `farm.transfers.index` — checked,
in reality, against Stores' `inventory.transfer.manage` instead — with
**no exception logged anywhere** and the real `Modules\Farm\Livewire\Transfers\Index::mount()`
never invoked at all (confirmed by direct instrumentation: granting the
*Stores* permission made the *Farm* route return 200).

Fixed by renaming the class end to end — namespace, directory, view
path, route import, test import — to `Modules\Farm\Livewire\KitchenTransfers\Index`,
rather than touching Stores' earlier-shipped, already-gated screen.

**Before naming any new module-root Livewire component** (one directly
under a module's own `Livewire/`, not nested under a sub-namespace like
`Maintenance\Reports\Index`), check for an existing collision first:

```
find . -name Index.php | grep Livewire | sed -E 's#.*/Livewire/##; s#/Index\.php$##' | sort | uniq -d
```

Any line printed is a real collision waiting to silently misroute two
modules' screens into one. This check found exactly one hit across the
whole codebase at the time this pass shipped (`Transfers`, now fixed) —
run it again before adding the next module-root screen anywhere.

## The withdrawal-period block (BR-OPS-03-012 ⭐⭐, AC-OPS-03-003) needed a UI field the first draft of this screen omitted

`TransferToKitchenAction` only runs its hard milk/meat withdrawal check
when the transfer is linked to a specific `ProductionOutput` row
(`$data->outputId !== null`) — a bare quantity/item transfer with no
`outputId` skips the check entirely, by design (not every kitchen
transfer is milk or meat). The screen's first draft had no `outputId`
field at all, which would have made the check structurally unreachable
through the admin UI. Fixed: `KitchenTransfers\Index` now offers an
*optional* "link to a milk/meat output" picker, populated only from the
selected unit's own `ProductionOutput` rows where `output_type` is
`milk`/`meat` — exercised end to end by this pass's own AC-OPS-03-003
test.

## `fiscal_receipt_id` is populated by a separate listener, not by `RecordFarmSaleAction` itself

`Sales\Index` calls `RecordFarmSaleAction` (Book H2 `OPS-03`), which
posts its own `Dr Cash / Cr Farm Sales Income` journal directly rather
than routing through `FIN-04`'s receipting engine, and leaves
`fiscal_receipt_id` null at the point it creates the row. **Correction
(2026-10-07)**: this used to be a genuine gap ("`FIN-13` doesn't exist
yet"), but `FIN-13` (`Modules\Fiscal`, Book H3) has since been built,
and a later gap-closing pass added
`Modules\Fiscal\Domain\Listeners\RouteFarmSaleListener` — registered on
the already-dispatched `FarmSaleRecorded` event in
`FiscalServiceProvider::registerListeners()` — which calls
`RouteReceiptForFiscalisationAction` and sets
`farm_sales.fiscal_receipt_id` for real. `RecordFarmSaleAction`'s own
docblock still says the old thing; read it in light of this correction.
The spec's own `AC-OPS-03-006` ("the sale is fiscalised through
FIN-13") is now literally true.

## Permissions are registered under ONE module code, `FARM`

The spec's own §5 table already names every permission under a single
`farm.*` namespace (`manage`/`crop.manage`/`record`/`livestock.manage`/
`transfer`/`sales.manage`/`report.view`).

## No gap-filling Actions were needed in this module

Unlike `Operations`/`Transport` in this same book, every `Modules\Farm`
table already had a real Action creating its rows before this pass
(verified: `CreateProductionUnitAction`, `CreateFarmFieldAction`,
`PlanCropCycleAction`, `RecordCropInputAction`, `RecordHarvestAction`,
`CreateLivestockAction`, `RecordLivestockEventAction`,
`RecordProductionOutputAction`, `TransferToKitchenAction`,
`RecordFarmSaleAction` — one per table, no exceptions).

## Admin-UI test file

`Modules/Farm/tests/Feature/Admin/FarmAdminUiTest.php`, own
`farmAdminFixture()`/`farmAdminUser()` pair — the fixture also stands up
a real farm store and kitchen store through `FIN-09`'s own
`CreateStoreAction` (both accounts required: `inventoryAccountId`,
`defaultExpenseAccountId`) and a `journal` numbering series, since
`RecordHarvestAction`/`TransferToKitchenAction` post real journals
through `Modules\Finance`. Four tests: a permission-refusal test, a
"renders every screen" smoke test, a dedicated AC-OPS-03-001 test (a
1,440/1,800kg cycle derives exactly 80 minor-unit cost per kg, and a
real `FIN-09` farm-store lot exists at that cost), and a dedicated
AC-OPS-03-003 test (a milk transfer linked to a cow still within its
recorded withdrawal period is blocked — no `InternalTransfer` row is
ever created for the attempt).
