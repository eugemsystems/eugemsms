---
paths:
  - 'Modules/Stores/**'
---

# Stores

Book H1 — Procurement, Stores, Assets & Budgets (`FIN-08`–`FIN-11`). All
four modules live in `Modules/Stores` despite the `FIN-` numbering
(verified against every migration's own docblock attribution) — this
was the first admin-UI pass for this module, so there was no prior
provider/route/permission scaffolding to extend.

## Book H1 admin-UI pass: what was built and deliberately deferred, by module

**FIN-09** ⭐ (16 screens, `Livewire/{Stores,Items,Stock,Receipts,
Requisitions,Transfers,StockTake,Anomalies,Reports}/`): see
`docs/specification/PROGRESS.md`'s own Book H1 entry for the full
per-screen breakdown. The blind-count discipline (BR-FIN-09-014,
AC-FIN-09-005) is structural, not a UI choice — `StockTake\Count`'s
count sheet is built exclusively from `GetBlindCountSheetAction`'s own
`BlindCountLine` DTO, which has no field to carry `system_quantity` at
all.

**FIN-08** 🇿🇼 (12 screens, `Livewire/Procurement/{Suppliers,
Requisitions,Quotations,Orders,Receipts,Invoices,Payments,Reports}/`):
the withholding-tax rate (`procurement.withholding_rate_percent`,
default 10.00%) is versioned, effective-dated configuration via
`SettingDefinitionRegistry`/`SettingResolver` — never a hard-coded
constant in any screen or Action. Tax clearance validity is always
assessed on the INVOICE date (BR-FIN-08-003), read via
`SupplierTaxClearance::isValidOn()`, never a single "current" flag.

**FIN-10** (8 screens, `Livewire/Assets/{Register,Depreciation,
Verification,Disposal,Insurance,Reports}/`): no screen in this module
writes `fixed_assets.net_book_value_minor`/`accumulated_depreciation_minor`
directly — `PostDepreciationRunAction` and `DisposeAssetAction` are
the only two paths that ever move either column, and both are
domain-layer Actions this pass only ever calls, never duplicates.

**FIN-11** ⭐ (6 screens, `Livewire/Budget/{Builder,Consolidation,
Variance,Commitments,Virement,Forecast}/`): `budget_lines.actual_minor`
has exactly one writer in the whole codebase —
`RecalculateBudgetLineActualsAction`, which only ever re-sums real
`journal_lines` rows (BR-FIN-11-008). No screen anywhere in this
module has a field to type an actual figure in by hand. The
`CreateBudgetCommitmentOnPurchaseOrderApprovedListener`/
`ReleaseBudgetCommitmentOnInvoiceMatchedListener` wiring from `FIN-08`
is real (registered in `StoresServiceProvider::registerEventListeners()`,
built in the backend pass before this one) — this admin-UI pass's own
`BudgetAdminUiTest` exercises it end to end through
`Procurement\Orders\Index`'s real `approve()` call, not a simulated
event dispatch.

## `ActionPatternEnforcementTest` caught a real violation on the first whole-app run — fixed with a new, narrow Action, not a CI exemption

`StockTake\Variance`'s own "save reason" control originally wrote
`StockTakeLine::where('id', $lineId)->update([...])` directly from the
Livewire component. `Modules\Core\tests\Feature\ActionPatternEnforcementTest`
(BR-GLOBAL-005 — no `->save()`/`->create()`/`->update()`/`->delete()`/
`DB::table()` anywhere under a module's own `Livewire/` directory)
failed on exactly this line in the first whole-app test run after this
pass's screens were written. Fixed with `RecordStockTakeVarianceReasonAction`
— the narrowest possible new Action (one field, no business logic
beyond the migration's own constraints) — rather than loosening the
CI rule or inlining the write any other way. Any future screen in this
module that needs to persist a single field with no existing Action
behind it should follow the same shape, not reach for `Model::update()`
directly no matter how trivial the write looks.

## No `UpdateXAction` exists anywhere in this module — every edit screen is create-only or a dedicated lifecycle transition

Backend-verified (`ls Modules/Stores/Domain/Actions/ | grep ^Update`
returns nothing): `Stores\Index`, `Items\Index` are both create-only,
matching the ACA-01/BRD-01 catalogue precedent from earlier books.
Every other mutation in this module is a named lifecycle transition
(`Approve*`, `Cancel*`, `Change*`, `Dispose*`, `Transfer*`, `Record*`)
rather than a generic field-by-field edit — there is no "edit a
purchase order" or "edit a fixed asset" screen because no such Action
exists, and inventing one would be a genuinely new backend decision,
not a UI retrofit.

## Permissions are registered under FOUR module codes — `INVENTORY`, `PROCUREMENT`, `ASSETS`, `BUDGET` — never `STORES`

`StoresServiceProvider::registerPermissions()` makes four separate
`PermissionRegistry::register($moduleCode, [...])` calls, the same
reasoning `.ai/rules/welfare.md` documents for its own three-way
`HEALTH`/`BEHAVIOUR`/`SAFEGUARDING` split: `$name = strtolower($moduleCode).'.'.$path`,
and the spec's own §9 permission lists for all four FIN modules
already name four distinct top-level namespaces (`inventory.*`/
`procurement.*`/`assets.*`/`budget.*`). Registering everything under
`STORES` would have produced `stores.inventory.store.view` etc.,
matching nothing any screen in this module actually checks. Any new
permission this module needs goes into one of the four existing
`PermissionRegistry::register()` calls, never a fifth.

## Permission names are a genuine mix of two- and three-segment shapes — split on the LAST dot for the action, not by position

Most permissions in this module are the conventional `resource.action`
two-segment shape (`store.view` → `inventory.store.view`), but several
are bare one-segment actions under their own module code
(`inventory.issue`, `assets.view`, `assets.manage`, `assets.transfer`,
`assets.verify`, `assets.dispose`, `budget.manage`, `budget.submit`,
`budget.consolidate`, `budget.view`). A test helper that destructures
a permission name via `explode('.', $name)` into exactly three parts
(the pattern several earlier books' own fixtures use) breaks on these
— every fixture in `Modules/Stores/tests/Feature/Admin/*.php` instead
splits on the LAST dot for the action and treats everything before the
first dot as the module code, the same fix `.ai/rules/boarding.md`
already documents for its own four-segment catering/linen permission
names.

## A screen that `authorizePermission()`s a resource-view permission in `mount()` gates every test user through it — grant it even to a "create-only" test user

`Procurement\Orders\Index::mount()` calls
`$this->authorizePermission('procurement.order.view')` unconditionally,
even though `create()`/`approve()` each additionally check their own
more specific permission. A test user granted only
`procurement.order.create` (intending to exercise just the create
flow) gets a 403 at mount and Livewire's test harness then reports a
confusing "Invalid Livewire snapshot structure" error on the FIRST
subsequent `->set()` call, not a clear 403 assertion failure — because
the initial mount response was never a valid component snapshot to
begin with. `BudgetAdminUiTest`'s own AC-FIN-11-001 test hit this
exactly; the fix is to grant the screen's own baseline `.view`
permission alongside whatever specific action permission a test needs,
mirroring how every "fully-permissioned user" test in this module's
own fixtures grants the whole permission list for that module in one
`UpdateUserPermissionsAction` call (which REPLACES the user's whole
grant set per call, never accumulates across calls).

## A query joining two `BelongsToSchool` models on a raw SQL `join()` collides on the unqualified `school_id` global-scope clause

`Procurement\Reports\Index`'s original spend-analytics tab joined
`supplier_invoices` to `suppliers` via `->join('suppliers', ...)` —
both models' own `BelongsToSchool` global scope injects an unqualified
`where('school_id', ...)` clause, and with two tables in the query
SQLite (and MySQL) both refuse it as an ambiguous column reference.
Fixed by removing the join entirely: `with('supplier')->get()->groupBy(...)`
in PHP over the already-scoped collection. Any future report screen in
this module that's tempted to join two tenant-scoped tables directly
should do the same — group/aggregate in PHP over eager-loaded
relations, not a raw SQL join, whenever both sides carry
`BelongsToSchool`.

## PHPStan's `nullsafe.neverNull` fires on `$model?->relation ?? default` when `$model` comes from a ternary around `Model::find()`

Four screens originally wrote `$x = $condition ? SomeModel::with(...)->find($id) : null;` followed by `'key' => $x?->relation ?? collect()`. Larastan infers the `find()` call's result as non-nullable in this specific chained-builder form, so it flags the `?->` as unnecessary — even though at runtime `find()` genuinely can return `null` for a missing id. Rather than fight the inference (no `@phpstan-ignore` is allowed in this codebase), every one of `Procurement\Quotations\Compare`, `Requisitions\ReturnItems`, `StockTake\Variance` was rewritten as an explicit `if ($x !== null) { ... }` block assigning a plain local variable — PHPStan accepts the explicit null check and the runtime behaviour is identical. `Reports\Valuation`'s analogous `$items[$id]->category?->name ?? 'Uncategorised'` hit the same rule on the `?->name` half (Larastan treats `->category` itself as resolved) and took the same explicit-`if` fix.

## Admin-UI test files, one per domain, in `tests/Feature/Admin/`

`InventoryAdminUiTest` (FIN-09), `ProcurementAdminUiTest` (FIN-08),
`AssetsAdminUiTest` (FIN-10), `BudgetAdminUiTest` (FIN-11) — each with
its own distinctly-named fixture function
(`inventoryAdminFixture`/`procurementAdminFixture`/`assetsAdminFixture`/
`budgetAdminFixture`), matching this codebase's established
one-fixture-per-file convention. Each file's own `*AdminUser()` helper
uses the last-dot-split permission parser described above. Every file
has: a permission-refusal test, a real business-rule test named after
its acceptance criterion (AC-FIN-09-001, AC-FIN-08-001,
BR-FIN-10-012/AC-FIN-10-005, AC-FIN-11-001), and a "renders every
screen for a fully-permissioned user" smoke test.
