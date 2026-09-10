---
paths:
  - 'Modules/People/Models/*.php'
---

# Models

## Give a model an explicit $table when the spec's table name doesn't pluralize the way Eloquent guesses
`staff_workload` (Book C PPL-04 §2) is spec'd singular — a per-term derived cache row, not a collection of "workloads" — but Eloquent's default table-name guess for `StaffWorkload` is `staff_workloads` (plural). Migrating to the spec's literal singular name without `protected $table = 'staff_workload';` on the model produces a "no such table: staff_workloads" failure that only surfaces at runtime (e.g. in `TenancyIsolationTest`), not at migration time.

How to apply: whenever a spec table name is irregular relative to Eloquent's pluralization (singular where a plural class name would guess otherwise, or any other mismatch), set `protected $table` explicitly on the model rather than trusting the convention. Model classes built this session that do NOT need the override, for calibration: `Staff`→`staff` (Str::plural('staff') === 'staff', matches), `Department`, `EstablishmentPost`, `StaffContract`, `TeacherAllocation`, `LeaveType`, `LeaveBalance`, `LeaveRequest` all pluralize correctly.

## Set $timestamps = false whenever the spec's table has no created_at/updated_at
Eloquent defaults every model to expecting both `created_at` and `updated_at` columns. A spec table that lists neither (many of Book C PPL-04's lookup/reference tables — `departments`, `establishment_posts`, `leave_types` — have no timestamp columns at all) will fail at insert time ("table X has no column named updated_at"), not at migration time, so it's easy to miss until a test actually creates a row.

Rule: whenever a migration's spec table has no `created_at`/`updated_at` columns, set `public $timestamps = false;` on the model. When the spec has only `created_at` (no `updated_at`) — e.g. `staff_contracts`, `teacher_allocations`, `leave_requests` — also use `$timestamps = false` and set `created_at` manually in the Action's create() call, matching this codebase's existing convention (see `StaffContract`, `TeacherAllocation`, `LeaveRequest`). Only use real Eloquent timestamps when the spec lists both columns (e.g. `Staff`, which has full `created_at, updated_at`).
