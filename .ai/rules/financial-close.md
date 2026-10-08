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

## What was built (originally 5 screens, `Livewire/{Financial,Close,Schedules,Export}/` — only screens with a real Action behind them; a later gap-closing pass added 3 more, see the correction below)

`Financial\TrialBalance`, `Financial\IncomeStatement` (folds the
spec's own separate "Point-in-time" screen in — see below),
`Close\Checklist` (folds the spec's own separate "Close pack"
screen in — see below), `Schedules\Index` (also hosts
`ReportDefinition` creation — see below), `Export\Accounting`.
The spec's own 12-screen table also names BalanceSheet/CashFlow/
Departmental/Collection/PriorPeriod/Board — at the time this pass
shipped, none of these had a backing Action, so none was built.
**Correction (2026-10-07)**: three of those four are now built — a later
gap-closing pass added `GenerateBalanceSheetAction`/`GenerateCashFlowAction`
plus `Financial\BalanceSheet`/`Financial\CashFlow` screens, and
`GenerateDepartmentalReportAction`/`GenerateCollectionReportAction`
folded into one new `Financial\Management` screen (tabbed
departmental/collection, per its own docblock) — all confirmed present
in `Modules/Reporting/Domain/Actions/` and `Modules/Reporting/Livewire/Financial/`.
**Further correction (2026-10-07):** "Board" was never actually unbuilt in the sense the line
above implied — `Modules\Intelligence\Domain\Actions\GenerateBoardPackAction`/
`Executive\BoardPack` (Book J INT-02) already assembles "standard statements plus enrolment... in
one document", the same concept BR-FIN-12-015 names under a different module/screen path. Rather
than build a second `Reports\Board\Pack` screen, this session added the one genuinely missing
piece instead — a `collection_rate` section, rolled up from this module's own real
`GenerateCollectionReportAction` — to that existing action/screen. FIN-12's own "key ratios" line
is still not built: the spec names no specific ratio list, and inventing one would be guessing at
a figure a school's board would actually rely on. **Further correction (2026-10-07):** "PriorPeriod" was never actually unbuilt either — read
literally, the spec's own "PriorPeriod" screen is BR-FIN-12-004/005's "point-in-time reporting...
lists the reconciling prior-period adjustments separately" rule, and `GenerateIncomeStatementAction`/
`Financial\IncomeStatement` already implemented exactly that (`asKnownOn`, `reconcilingItems`,
`reconcilingTotalMinor`) — confirmed by reading the actual code, not a docblock claim. The one real
gap was that `GenerateTrialBalanceAction`/`Financial\TrialBalance` had the `asKnownOn` *filter* but
never surfaced the reconciling items *separately* the way Income Statement did — a straight
BR-FIN-12-005 violation for that one report ("never blended" in, which is exactly what a bare
filter does). Closed this session: `GenerateTrialBalanceAction` now returns a `TrialBalanceResult`
(`rows`/`reconcilingItems`, mirroring `IncomeStatementResult`'s own shape) and
`Financial\TrialBalance` shows the same "prior-period adjustments, posted after..." card Income
Statement already had. Nothing in FIN-12's own 12-screen table remains genuinely unbuilt except
"key ratios" (part of the spec's own Board pack wording, no ratio list given to build against —
see the Board correction above).

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
