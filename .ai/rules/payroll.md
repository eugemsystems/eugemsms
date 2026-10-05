---
paths:
  - 'Modules/Payroll/**'
---

# Payroll

## Carbon's diffInDays() returns a noisy float — round before feeding bcmath
`CarbonInterface::diffInDays()` between a start-of-day instant and an end-of-day instant (e.g. `startOfMonth()` to `endOfMonth()`) returns a float that lands a hair below the true integer — observed as `29.999999999988` for what should be exactly 29 — because Carbon computes the diff from sub-second-precision timestamps.

Casting that float straight to a string and feeding it into `bcsub($x, $y, 1)` (scale 1) TRUNCATES rather than rounds, silently losing about a tenth of a day. In `UnpaidLeaveProrationCalculator` this produced a bogus proration factor (~0.9967 instead of exactly 1) and a wrong payslip gross even with zero unpaid-leave rows — the bug looked like a leave-query problem but was actually float noise from `diffInDays()`.

Fix: `(int) round($start->diffInDays($end))` before any bcmath touches it, never a raw cast. Applies to any day-count arithmetic derived from `diffInDays()`/`diffInHours()` etc. feeding bcmath — round to the nearest sane unit first.

---

## Admin-UI pass (Book H3 PPL-05)

Book H3 `PPL-05` — Payroll & Statutory Deductions. Admin-UI pass,
first of this book's four (PPL-05 → FIN-13 → FIN-14 → FIN-12, the
book's own build order).

## What was built (11 screens, `Livewire/{Statutory,Grades,Components,Staff,Loans,Run,Payslips,Returns,Reports}/`)

`Statutory\Config` ⭐⭐ (the single most statutory-dense screen in the
build — see below), `Grades\Index` (also hosts notch creation),
`Components\Index` (tax-treatment flags), `Staff\Structure` (dated,
also hosts `AddStaffPayComponentAction`), `Loans\Index`, `Run\Wizard`
⭐ (folds the spec's own separate "Preview" screen into one
compute → approve → post → distribute lifecycle), `Run\BankFile`
(generates the bank CSV and records payment), `Payslips\Show` (the
calculation trace, compensation-gated), `Returns\Index`,
`Returns\Itf16`, `Reports\Summary` (named `Summary`, not the spec's
own bare `Index` — see below).

## ⭐⭐ Every statutory figure is read from `statutory_configurations`, never hard-coded — verified by name

`StatutoryConfigResolver::resolve()`/`resolveOptional()` is the ONLY
place a PAYE band, AIDS Levy rate, NSSA POBS/APWCS rate, ZIMDEF rate
or NEC due is ever looked up, keyed by `config_type` (`paye_bands`,
`aids_levy`, `nssa_pension`, `nssa_apwcs`, `zimdef`, `nec_dues`,
`withholding`, `credits`), `school_id`, pay date and currency.
`Statutory\Config` is the ONLY screen that writes to this table
(`CreateStatutoryConfigurationAction`/`ConfirmStatutoryConfigurationAction`)
— no literal rate appears anywhere in this module's Livewire or Blade
files. The `configurationJson` field is a raw JSON textarea (not a
structured band-table editor — a deliberate scope cut, see "Deferred"
below) validated server-side as decodable JSON before being passed
straight into `CreateStatutoryConfigurationData->configuration`. A
row flagged `requires_confirmation` shows a live, un-dismissable
banner (the same `requires_confirmation` pattern `Academic\Curriculum\Frameworks`
already established) until `ConfirmStatutoryConfigurationAction`
runs. `PayrollAdminUiTest`'s own `'reads PAYE from versioned
configuration...'` test proves this end-to-end **through the real
`Statutory\Config` screen**, not by calling the Action directly:
compute a run under one PAYE band, supersede the band table through
the Livewire component, compute a second run under a later pay date,
and confirm the PAYE total actually changes — found and fixed a real
bug in the process (see next section).

## A real, pre-existing backend bug found and fixed in this pass: date-boundary comparisons against a `date`-cast column

`StatutoryConfigResolver::find()` compared `effective_from`/
`effective_to` with a plain `where('col', '<=', $date->toDateString())`.
On SQLite (the test suite's own DB driver), a `date`-cast column gets
written with a full `Y-m-d 00:00:00` timestamp, and a bare
lexicographic string comparison against a bare `Y-m-d` value then
fails exactly on the boundary date (`'2026-11-01 00:00:00' <= '2026-11-01'`
is **false** — the longer string sorts after its own prefix). This
silently excluded a brand-new statutory configuration effective
*today*, resolving the prior (often wrong) rate instead — found only
because the admin-UI test above asserted the new PAYE total was
actually higher, not merely that a row existed. Fixed by switching to
`whereDate(...)`/`orWhereDate(...)`, which wraps both sides in the
driver's own `DATE()` function. The same exact pattern was found and
fixed in two more places that share this bug (both in this module,
both narrowly in scope): `ComputePayslipAction::activeStructure()`
and `PayComponentResolver::resolve()`. A fourth occurrence,
`Modules\Reporting`'s `GenerateAccountingExportAction`'s overlap
check, was fixed too (see `.ai/rules/financial-close.md`). Several OTHER
date-range queries in this book already used `whereDate(...)`
correctly (`GenerateTrialBalanceAction`, `GenerateIncomeStatementAction`)
— this bug was narrow, not systemic, but worth grep-ing for before
writing any new `effective_from <= $x->toDateString()`-shaped query
in this codebase.

## One new gap-filling Action: `CreatePayGradeNotchAction`

`pay_grade_notches` had a real migration/model/factory but no Action
anywhere in the domain layer ever created a row — only
`PayGradeNotchFactory`, called from `PayrollServiceProvider`'s own
tenancy-isolation-test registration, ever did (verified by grep). The
same "model exists, no Action ever wrote one" gap every prior book's
admin-UI pass has hit at least once. Added as a small, narrow,
create-only Action (`CreatePayGradeNotchData`), mirroring
`CreatePayGradeAction`'s own plain-create shape exactly, and folded
into `Grades\Index` rather than given its own route.

## The 15-account `PayrollGlAccounts` bundle: 14 by `system_key`, 1 by a targeted dropdown

`PostPayrollRunAction` requires every GL account explicit — no
system-account auto-provisioning, matching this codebase's
established financial-posting convention. `Run\Wizard`'s own `post()`
resolves 14 of them by a dedicated `payroll_*` `system_key` (via
`Finance\Livewire\Concerns\ResolvesSystemAccounts`, the same
mechanism FIN-01/06 already use for `rounding`/`fx_unrealised_gain`)
— a bursar posting payroll monthly should not re-pick fourteen
accounts every run; `requireSystemAccount()` aborts 422 by name if
one isn't configured yet, directing the bursar to Chart of Accounts.
The fifteenth, Fee Debtors, has no system key anywhere in this
codebase (the same "no system key for income/refundable-deposits
specifically" gap `PPL-02`/`PPL-03` already documented) — picked
instead from accounts flagged `is_control_account`/
`subledger_type = 'student'`, a dropdown narrow enough to only ever
show the real fee debtors control account(s). `Wallet\Wallets\Show`/
`TermEnd\Process` reuse the exact same dropdown query for the same
reason (see `.ai/rules/wallet.md`).

## `payroll.require_separate_approver` is enforced in the UI, not the Action

`ApprovePayrollRunAction`'s own docblock states permission/identity
enforcement is deliberately the caller's job. `Run\Wizard::approve()`
reads the setting via `SettingResolver` and refuses (toast, no
Action call) when the current user is the same one who computed the
run — this is the actual enforcement mechanism for BR-PPL-05-013's
"Head + Bursar minimum" intent; the Action itself has no check to
defeat.

## Compensation visibility: the same `canViewCompensation()` pattern `People\Staff\Show` already established

`Payslips\Show::canViewCompensation()` checks
`people.staff.view_compensation` via `PermissionScopeResolver`
directly (never an abort) — the Blade view hides the entire earnings/
deductions/trace block for a viewer without it, leaving only the
payslip number visible, per AC-PPL-05-010's "absent... not merely
hidden."

## `Reports\Summary` is named `Summary`, not the spec's own bare `Index`

`Modules\Farm\Livewire\Reports\Index` already occupies that exact
relative path (Book H2 OPS-03) — caught by the standing
duplicate-component-name check before this file was written, the
same `KitchenTransfers`/`Houses\Leaderboard` precedent.

## Deferred against the full spec (all pre-existing backend boundaries, not new cuts)

Split-currency salary blending (`usd_portion_percent`/
`zwg_portion_percent`), `percentage_of_gross`/`formula`/`hourly`/
`per_unit` calculation methods (the `Components\Index` dropdown
offers only `fixed`/`percentage_of_basic`, the two
`PayComponentResolver` actually supports), terminal pay's distinct
computation path, and a structured PAYE-band editor (a raw JSON
textarea stands in — the band table shape is genuinely
variable-length, and a dynamic repeater control was judged not worth
building for this pass's effort budget). None of these are UI-pass
decisions; each is named in the relevant backend Action's own
docblock.

## Permissions are registered under ONE module code, `PAYROLL`

`view`, `manage` ⚠, `staff.manage` ⚠, `statutory.manage` ⚠⚠, `run` ⚠,
`approve` ⚠, `post` ⚠, `pay` ⚠, `loan.manage`, `returns.manage`,
`report.view`, `reverse` ⚠ (registered per the spec's own table even
though no screen in this pass exercises it — a posted run's
correction path has no dedicated screen yet).

## Admin-UI test file

`Modules/Payroll/tests/Feature/Admin/PayrollAdminUiTest.php`, own
`payrollAdminFixture()`/`payrollAdminUser()`/`payrollAdminStaff()`
trio — `ppl05Fixture()`/`createPayrollStaff()` already exist in the
sibling backend test file and Pest loads every file together in a
full suite run. Three tests: a permission-refusal test, a "renders
every screen" smoke test, and the statutory-rate-from-configuration
proof described above.
