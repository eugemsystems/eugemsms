---
paths:
  - 'Modules/Wallet/**'
---

# Wallet

Book H3 `FIN-14` — Student Wallet & Tuckshop. Admin-UI pass, third of
this book's four (PPL-05 → FIN-13 → FIN-14 → FIN-12).

## What was built (8 screens, `Livewire/{Pos,Products,SpendPoints,Wallets,TermEnd,Reports}/`)

`Pos\Terminal` ⭐ (the touch-grid sale screen — see below),
`Products\Index`, `SpendPoints\Index`, `Wallets\Index`/`Wallets\Show`
(named `Wallets`, not the spec's own `Accounts` — see below),
`TermEnd\Process` ⚠ (preview-then-commit, per BR-FIN-14-015),
`Reports\Reconciliation` ⭐ (runs the real
`ReconcileWalletLiabilityAction` on demand), `Reports\Sales`
(read-only analytics).

## `Wallets\Index`/`Wallets\Show`, not `Accounts\Index`/`Accounts\Show`

`Modules\Utilities\Livewire\Accounts\Index` already occupies
`Accounts/Index` at the exact relative path Livewire resolves
full-page components by (Book H2 OPS-04) — caught by the standing
duplicate-component-name check before this file was written, the
same `KitchenTransfers`/`Houses\Leaderboard`/`Reports\Summary`
precedent this book and the last both set.

## `Pos\Terminal`'s "sale happened offline" toggle is an honest stand-in, not a real offline cache

There is no real browser-side offline queue/service worker built in
this pass — the spec's own device-level cache (§4) is simplified to
one checkbox that routes the identical sale through
`SyncOfflineWalletSaleAction` (with a client-generated
`offline_reference`) instead of `ProcessWalletSaleAction`. That is
the one real behavioural difference between the two paths that
actually matters for this pass: an offline sync is honoured even
against a since-changed insufficient balance, going negative within
the configured limit and notifying the guardian immediately
(BR-FIN-14-012), where the online path refuses outright. Also hosts
void (`VoidWalletSaleAction`) for the operator's own recent sales —
no dedicated "sales" screen exists separately for this.

## GL account resolution: one system key, one targeted dropdown — the same split `Payroll\Run\Wizard` uses

A wallet's `liability_account_id` resolves by the dedicated
`wallet_liability` `system_key` (`Wallets\Index::create()`) — there
can be hundreds of learner wallets, all against the exact same
liability account, so a system key removes a per-wallet dropdown
entirely. Fee Debtors (on `Wallets\Show`'s close control and
`TermEnd\Process`) has no system key anywhere in this codebase (the
same "no system key for income/refundable-deposits specifically" gap
`PPL-02`/`PPL-03` already documented) — picked instead from accounts
flagged `is_control_account`/`subledger_type = 'student'`, the
identical dropdown query `Payroll\Run\Wizard` uses for the same
reason. A top-up's clearing account (`Wallets\Show::topUp()`) is a
plain postable-accounts pick, since it legitimately varies by
payment method (cash vs bank vs gateway) — no system key would fit.

## `SetWalletControlsAction` is exercised through a guardian picker, not bypassed

Spending controls are set by staff on behalf of the student's own
fee-responsible guardian — the same staff-recorded stand-in
`Boarding\Exeats\Index` already uses for an unbuilt guardian portal.
`Wallets\Show`'s guardian dropdown is filtered to
`is_fee_responsible = true`/`status = active` links only;
`SetWalletControlsAction` itself still independently requires a real
link (`WalletControlRightRequiredException`), never bypassed by this
screen.

## No new gap-filling Actions were needed in this module

Every table already had a real create Action.

## Permissions are registered under ONE module code, `WALLET`

`sell`, `manage` ⚠ (shared by `Products\Index`/`SpendPoints\Index`,
not individually dangerous, and `TermEnd\Process`, which is — the
permission takes the more cautious of its own uses, the same
convention `wallet.manage`'s spec table entry already implies),
`view`, `report.view`.

## Admin-UI test file

`Modules/Wallet/tests/Feature/Admin/WalletAdminUiTest.php`, own
`walletAdminFixture()`/`walletAdminUser()` pair — `fin14Fixture()`
already exists in the sibling backend test file. Three tests: a
permission-refusal test, a "renders every screen" smoke test, and an
AC-FIN-14-002 test through the real `Pos\Terminal` screen (a
guardian-blocked category is refused at `submitSale()`, the toast
names the category, and the wallet balance is left untouched).
