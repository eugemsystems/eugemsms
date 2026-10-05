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
`Audit\Index` (read-only — see the documented gap below).

## `Devices\Index` folds in Certificate lifecycle

No `RenewCertificateAction` exists distinct from
`RegisterFiscalDeviceAction` itself — a real ZIMRA certificate
renewal is registering a fresh device (a brand-new `device_id` ZIMRA
assigns), and `environment` is fixed forever on an existing device
per BR-FIN-13-016, so there is no "edit" to give a separate screen.
Expiry/status show on each device's own row; `CheckCertificateExpiryAction`'s
own on-demand scan (uncronned, matching this book's other periodic
scans) is the honest stand-in for a scheduled alert.

## `Receipts\Retry` also hosts manual credit-note raising

`RaiseFiscalCreditNoteAction` is already wired automatically from
`Modules\Wallet`'s `VoidWalletSaleAction` — but a farm-sale or
facility-hire void has no such wiring (both documented backend
boundaries, not this pass's to close). This screen's own "raise
credit note" control on an accepted receipt is the manual fallback
for those paths, placed here rather than on a new route since
correction is already this screen's theme.

## A real, documented backend gap found but NOT closed this pass: `fiscal_audit_log` has no writer anywhere

`fiscal_audit_log` has a real migration/model/factory, but no Action
in the domain layer (`RegisterFiscalDeviceAction`, `OpenFiscalDayAction`,
`CloseFiscalDayAction`, `SubmitFiscalReceiptAction`,
`DrainOfflineFiscalQueueAction`, `CompileAndSubmitZReportAction`)
ever writes a row to it — verified by grep; only
`FiscalAuditLogEntryFactory`, called from `FiscalServiceProvider`'s
own tenancy-isolation-test registration, ever creates one.
BR-FIN-13-012 ("every request and response is logged verbatim...
append-only") is therefore not actually implemented despite being a
named rule. Unlike this book's two narrow create-only gap-fills
(`CreatePayGradeNotchAction`, `CreateReportDefinitionAction`), closing
this one needs wiring a log write into SIX existing call sites — real
business logic broader than this pass's gap-filling mandate — so it
is surfaced here honestly rather than improvised past. `Audit\Index`
is built and ready; it renders correctly empty today.

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
