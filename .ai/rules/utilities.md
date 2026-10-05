---
paths:
  - 'Modules/Utilities/**'
---

# Utilities

Book H2 `OPS-04` — Utilities & Energy Management 🇿🇼. Admin-UI pass
built first in this sub-pass's own order (OPS-04 → OPS-05 → OPS-06 →
OPS-07, independent of each other per the book's own build order —
see `01-volume1...`/book H2 §0.2).

## What was built (10 screens, `Livewire/{Accounts,Meters,Tokens,Readings,Generators,GeneratorRuns,Solar,Water,LoadShedding,Dashboard}/`)

`Accounts\Index`, `Meters\Index` (scope, cost centre, current
balance), `Tokens\Index` ⭐ (purchase, confirm credit, uncredited
queue button, monthly reconciliation button), `Readings\Index`
(append-only; anomaly flag shown immediately), `Generators\Index`,
`GeneratorRuns\Index` (start/stop, diesel draw from a real `FIN-09`
store), `Solar\Index` (installation register + generation log),
`Water\Index` (sources, readings, quality tests — folds three of the
spec's data concerns into one screen since they share one register),
`LoadShedding\Index` (published schedule or observed actual, same
form), `Dashboard\Index` ⭐ (folds the spec's separate "Consumption
analysis" screen into this one read, alongside the cost-of-outage
figure — `ComputeOutageCostAction`'s own `OutageCostResult`).

## 🇿🇼 The statutory/regulatory figure in this module is NOT a hard-coded constant

This module has no ZIMRA tax band or NSSA-style rate — its own 🇿🇼 is
the *load-shedding economy* itself, not a single number requiring
periodic external revision. Every number this module's business
rules depend on (`utilities.token_credit_window_hours`,
`utilities.generator_load_factor`,
`utilities.borehole_yield_alert_percent`, etc.) is already versioned,
effective-dated configuration via `SettingDefinitionRegistry`/
`SettingResolver` — confirmed by reading every Action in this module:
none of them reads a bare literal for a business-rule threshold.
Nothing in this pass needed correcting from training-data assumptions.

## Permissions are registered under ONE module code, `UTILITIES`

`manage` (accounts/meters/solar/water/load-shedding — all create-only
registers with no further lifecycle), `token.record` ⭐ (purchase and
confirm credit — a 🇿🇼-flagged permission in the spec's own table),
`read` (meter readings), `generator.manage`/`generator.record`
(register vs. operate, matching the spec's own split), `report.view`
(dashboard).

## No gap-filling Actions were needed in this module

Every `Modules\Utilities` table already had a real Action creating
its rows before this pass (verified: `CreateUtilityAccountAction`,
`CreateMeterAction`, `PurchasePrepaidTokenAction`,
`RecordMeterReadingAction`, `CreateGeneratorAction`,
`StartGeneratorRunAction`/`StopGeneratorRunAction`,
`CreateSolarInstallationAction`, `RecordSolarGenerationAction`,
`CreateWaterSourceAction`, `RecordWaterReadingAction`,
`RecordWaterQualityTestAction`, `RecordLoadSheddingAction`). None of
them have an `Update` counterpart (every one is a register-once row;
status changes happen inline via lifecycle actions like
`StopGeneratorRunAction`), and none needed one for this pass's own
screens.

## A Livewire-hydration gotcha: do not store a plain DTO as a public component property

`Dashboard\Index` originally stored `ComputeOutageCostAction`'s own
`OutageCostResult` readonly DTO directly as a public property. Livewire's
property synthesizer has no synth for an arbitrary plain object and
throws `Property type not supported in Livewire for property: [...]`
on the very first round trip. Fixed by exploding the DTO into scalar
public properties (`gridKwh`, `gridCostMinor`, `generatorKwh`, …) set
in `compute()` — the same pattern to use for any future screen that
wants to keep a computed read-model's result across a `wire:click`
without a full-page reload. `Facilities\Utilisation\Index` hit the
same shape of problem with an array containing a `BookableResource`
Eloquent model nested inside — fixed by extracting scalar fields
(`resourceCode`/`resourceName`) instead of keeping the model.

## Admin-UI test file

`Modules/Utilities/tests/Feature/Admin/UtilitiesAdminUiTest.php`, own
`utilitiesAdminFixture()`/`utilitiesAdminUser()` pair. Three tests: a
permission-refusal test, a "renders every screen" smoke test, and a
dedicated AC-OPS-04-001 test (a token purchased 30 hours ago and never
credited appears on the uncredited queue after `checkUncredited`,
while its own row stays `status = purchased`).
