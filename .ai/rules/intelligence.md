---
paths:
  - 'Modules/Intelligence/**'
---

# Intelligence

## Report builder: every filter, group-by and selected field is checked against the runner, server-side
ExecuteCustomReportAction must validate each filter and group-by field (registered, filterable/groupable, readable by the RUNNING user) as well as each selected field — a predicate over an unreadable field (e.g. WHERE basic_salary_minor > N) is an inference oracle even if the column is never selected. Column aliases are concatenated into SQL, so they must match /^[A-Za-z_][A-Za-z0-9_]{0,63}$/. RunSavedReportAction only runs for the author or a share recipient (user or role). Field permission checks go through ReportFieldAccess, which treats a never-created permission as "not permitted" (spatie throws PermissionDoesNotExist otherwise). Livewire properties are client-tamperable: UI pickers are a convenience, the Action is the defence.
