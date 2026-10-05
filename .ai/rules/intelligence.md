---
paths:
  - 'Modules/Intelligence/**'
---

# Intelligence

## Report builder: every filter, group-by and selected field is checked against the runner, server-side
ExecuteCustomReportAction must validate each filter and group-by field (registered, filterable/groupable, readable by the RUNNING user) as well as each selected field — a predicate over an unreadable field (e.g. WHERE basic_salary_minor > N) is an inference oracle even if the column is never selected. Column aliases are concatenated into SQL, so they must match /^[A-Za-z_][A-Za-z0-9_]{0,63}$/. RunSavedReportAction only runs for the author or a share recipient (user or role). Field permission checks go through ReportFieldAccess, which treats a never-created permission as "not permitted" (spatie throws PermissionDoesNotExist otherwise). Livewire properties are client-tamperable: UI pickers are a convenience, the Action is the defence.

## Early-warning data is staff-only; wellbeing is own + line manager, no override
INT-03 risk scores/factors/flags are never exposed to learner or guardian surfaces and nothing contacts a guardian (BR-INT-03-002/004). Staff wellbeing goes through StaffWellbeingVisibility (own record + reports_to_staff_id reports) for every read AND write — no school-wide override, re-derived per call. Weights only for registered learner indicators, bounded 0-100 (SetRiskScoreWeightAction). Flag closure needs an intervention note and a closed flag cannot be re-reviewed. Screens live under Livewire/EarlyWarning/.

## Webhook targets: public https only, re-checked at every dispatch
Webhook URLs are server-side-fetched, so WebhookTargetUrl::assertSafe() runs on create AND on every DispatchWebhookAction attempt (https only, no credentials, no localhost/private/link-local/reserved IPs, DNS-resolved addresses checked, redirects never followed). A subscription's client must be an active integration client of the same school. API keys, signing secrets and SSO credentials are shown once / write-only — never reloaded into a screen. The Clients screen only touches client_type=integration; device credentials belong to the Hardware screen.
