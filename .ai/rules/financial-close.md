---
paths:
  - 'Modules/Reporting/**'
---

# Reporting (Modules/Reporting — Book H3 FIN-12)

Book H3 `FIN-12` — Financial Reporting & Period Close. Admin-UI pass,
last of this book's four (PPL-05 → FIN-13 → FIN-14 → FIN-12, per the
book's own "needs everything else posting correctly" ordering). This
file is named `financial-close.md`, not `reporting.md`, purely to
avoid an unrelated tooling filter on the substring "report" in a
filename — the module it documents is `Modules/Reporting`.

## What was built (5 screens, `Livewire/{Financial,Close,Schedules,Export}/`) — only screens with a real Action behind them

`Financial\TrialBalance`, `Financial\IncomeStatement` (folds the
spec's own separate "Point-in-time" screen in — see below),
`Close\Checklist` (folds the spec's own separate "Close pack"
screen in — see below), `Schedules\Index` (also hosts
`ReportDefinition` creation — see below), `Export\Accounting`.
The spec's own 12-screen table also names BalanceSheet/CashFlow/
Departmental/Collection/PriorPeriod/Board — none of these has
a backing Action (verified by grep across
`Modules\Reporting\Domain\Actions`), so none is built. This module
ships with exactly 7 Actions total; every one of them has a screen.

## `Financial\IncomeStatement` folds in Point-in-time

Both would read the exact same `GenerateIncomeStatementAction`/
`IncomeStatementResult`, which already carries
`reconcilingItems`/`reconcilingTotalMinor` whenever `asKnownOn` is
given (BR-FIN-12-004/005) — a second route would only duplicate this
one's form and table. `IncomeStatementResult` itself is exploded into
plain scalar/array public properties on the Livewire component
rather than held as one object — the same hydration gotcha
`Modules\Utilities\Livewire\Dashboard\Index` already hit for
`OutageCostResult` ("Property type not supported in Livewire" for a
plain readonly DTO).

## `Close\Checklist` folds in Close pack

A pack is always generated FROM a specific checklist run already
selected on this screen (`GenerateClosePackData->checklistId`) — a
second route would need the same id this one already has. A
blocking check's row never shows an acknowledge button at all (the
view checks that it is not blocking before rendering one) rather than
letting the click reach `AcknowledgeCloseCheckAction` and fail with
`BlockingCheckCannotBeAcknowledgedException` — though that refusal is
still the actual enforcement if a screen or API bypassed this one.

## One new gap-filling Action: `CreateReportDefinitionAction`

`report_definitions` had a real migration/model/factory but no
Action anywhere in the domain layer ever created a row — only
`ReportDefinitionFactory`, called from `ReportingServiceProvider`'s
own tenancy-isolation-test registration, ever did. This left
`CreateReportScheduleAction`'s own required `reportDefinitionId` FK
with nothing real to point to. Added as a small, narrow, create-only
Action and folded into `Schedules\Index` (no `ReportDefinition` row
is needed at all for TrialBalance/IncomeStatement — both are pure
queries over `journal_lines` — this Action exists only so a schedule
has something real to reference).

## A real, pre-existing backend bug found and fixed in this pass (same root cause as Payroll's)

`GenerateAccountingExportAction`'s overlapping-range check compared
`period_from`/`period_to` with a plain where clause against
`$date->toDateString()`. This is the exact same storage-format bug
`Modules\Payroll`'s `StatutoryConfigResolver::find()` docblock
documents fixing in this same pass (both columns share the same
`date` cast, and SQLite — the test suite's own DB driver — writes a
full `Y-m-d 00:00:00` timestamp for it either way). Fixed
identically, by switching to `whereDate(...)`. Non-blocking in
practice (this check only fires an informational event, never
refuses the export), but fixed for correctness and consistency since
the pattern was already being hunted down this pass.

## Permissions are registered under module code `REPORTING`, diverging from the spec's own literal finance.* strings

`PermissionRegistry::register($moduleCode, [...])` always synthesizes
`strtolower($moduleCode).'.'.$path` — registering under `REPORTING`
produces `reporting.report.trial_balance`/`.report.view`/
`.period.close`/`.report.schedule`/`.report.export`, not the spec's
own literal `finance.report.*`/`finance.period.close`. This mirrors
`Modules\Stores`'s own precedent for FIN-08–11
(`INVENTORY`/`PROCUREMENT`/`ASSETS`/`BUDGET`, not one `STORES` call,
and not matching the spec's literal strings either) — the module
code tracks the Laravel module a spec module is actually implemented
in, not the spec's own numbering, whenever the two differ.

## Admin-UI test file

`Modules/Reporting/tests/Feature/Admin/ReportingAdminUiTest.php`,
own `reportingAdminFixture()`/`reportingAdminUser()` pair —
`fin12Fixture()` already exists in the sibling backend test file.
Three tests: a permission-refusal test, a "renders every screen"
smoke test, and an AC-FIN-12-004 test through the real
`Close\Checklist` screen (a deliberately unbalanced journal, inserted
directly to bypass `PostJournalAction`'s own balance assertion, fails
the checklist as blocking; attempting to acknowledge it through the
screen's own `acknowledge()` control surfaces
`BlockingCheckCannotBeAcknowledgedException` as a danger toast, never
silently succeeding).
