---
paths:
  - 'Modules/Fiscal/**'
---

# Fiscal

Book H3 `FIN-13` — ZIMRA Fiscalisation (FDMS). Admin-UI pass, second
of this book's four (PPL-05 → FIN-13 → FIN-14 → FIN-12).

## What was built (9 screens, `Livewire/{Devices,Rules,Days,Receipts,Queue,Reports,Reconciliation,Audit}/`)

`Devices\Index` (folds the spec's own separate "Certificate
lifecycle" screen in — see below), `Rules\Index` (the routing
engine — every row requires a rationale and reviewing accountant at
creation, matching `CreateFiscalisationRuleAction` exactly), `Days\Index`
(open/close + a "compile Z-report" control once closed), `Receipts\Index`
(read-only monitor), `Receipts\Retry` ⭐ (failed/rejected retry +
manual credit-note raising — see below), `Queue\Status` (offline
queue depth/oldest/drain), `Reports\ZReports` (read-only), `Reconciliation\Index`
⭐ (runs the real `ReconcileFiscalisationAction` on demand),
`Audit\Index` (read-only; rows written by the gateway decorator).

## `Devices\Index` folds in Certificate lifecycle

No `RenewCertificateAction` exists distinct from
`RegisterFiscalDeviceAction` itself — a real ZIMRA certificate
renewal is registering a fresh device (a brand-new `device_id` ZIMRA
assigns), and `environment` is fixed forever on an existing device
per BR-FIN-13-016, so there is no "edit" to give a separate screen.
Expiry/status show on each device's own row. **Correction (2026-10-07)**:
`CheckCertificateExpiryAction` is now genuinely scheduled —
`fiscal.check_certificate_expiry` registered with
`ScheduledTaskHandlerRegistry`, given a real cron entry by
`routes/console.php`'s generic loop, closed in the project-wide
scheduled-jobs gap-closing pass. It was previously documented here as
on-demand-only; that's no longer accurate.

## `Receipts\Retry` also hosts manual credit-note raising

`RaiseFiscalCreditNoteAction` is already wired automatically from
`Modules\Wallet`'s `VoidWalletSaleAction` — but a farm-sale or
facility-hire void has no such wiring (both documented backend
boundaries, not this pass's to close). This screen's own "raise
credit note" control on an accepted receipt is the manual fallback
for those paths, placed here rather than on a new route since
correction is already this screen's theme.

## `fiscal_audit_log` is written by `AuditedFiscalGatewayDriver`

BR-FIN-13-012 is implemented by a decorator around the bound
`FiscalGatewayDriver` (`FiscalServiceProvider::register()`), not by log
writes in each Action. Any new driver is wrapped automatically; never call
a driver that bypasses the binding. Private key refs and certificate bodies
are deliberately not logged, and a failed log write is swallowed so it can
never block a fiscal call. `http_status` stays null until a real driver
exposes it through the contract.

## Permissions are registered under ONE module code, `FISCAL`

`device.manage` ⚠⚠, `rules.manage` ⚠, `day.manage`, `view`, `retry`,
`audit.view`.

## Admin-UI test file

`Modules/Fiscal/tests/Feature/Admin/FiscalAdminUiTest.php`, own
`fiscalAdminFixture()`/`fiscalAdminUser()` pair — `fin13Fixture()`
already exists in the sibling backend test file. Three tests: a
permission-refusal test, a "renders every screen" smoke test, and an
AC-FIN-13-001/002 test (simulate FDMS unreachable via the real
`FakeFiscalGatewayDriver`'s `last_ping_status = 'offline'` control —
not the receipt-level `_simulate` flag, which persists on the
payload and would block retry forever — confirm the receipt queues
offline and shows on `Queue\Status`, flip the device back online,
drain through the screen's own `drain()` control, confirm acceptance
and an empty queue).
