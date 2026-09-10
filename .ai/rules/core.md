---
paths:
  - 'Modules/Core/**'
  - 'Modules/Core/**/*.php'
---

# Core

## BelongsToSchool models: never query them by ID without withoutGlobalScopes() unless ambient SchoolContext is guaranteed to match
The single most common bug across CORE-02 and CORE-03 this build: any model using `BelongsToSchool` (Term, AcademicYear, PeriodRollover, PeriodReopenRequest, PeriodSnapshot, SchoolSection, GradeLevel, SchoolClass, House, SchoolModule, TermWeek, CalendarHoliday, PeriodStateTransition...) carries a global scope that filters every query by `SchoolContext::currentId()`. If that context isn't set — which is the default in a Pest test, a queued job, a roll-over handler, or any action called from a different school's context than the record itself belongs to — `Model::query()->find($id)` / `Model::where(...)` silently returns nothing (or throws ModelNotFoundException), not an authorization error.

**Rule**: any Action that receives an explicit ID for a BelongsToSchool model and does NOT already know for certain that ambient SchoolContext matches that record's school must load it via `Model::withoutGlobalScopes()->findOrFail($id)` (or `->where('id', $id)->where('school_id', $schoolId)->firstOrFail()` when cross-checking a claimed parent). This includes IDs reached through a relation on an already-scope-bypassed model — `$parent->relatedModel` still re-applies the related model's own global scope; bypass it by querying the related model directly by its foreign key column instead (`Related::withoutGlobalScopes()->find($parent->related_id)`).

**Why**: caught repeatedly via real Pest test failures (ModelNotFoundException / "already pending" checks that never trip / "already running" locks that never trip) — every one of these was silent in application code and only surfaced because a test exercised the actual code path without pre-seeding SchoolContext. `School`, `Tenant`, and `User` are NOT scoped (they're the tenancy anchors) and never need this.

**How to apply**: when writing a new Action under `Modules/Core/Domain/Actions/**` that takes an id/DTO referencing a `BelongsToSchool` model, default to `withoutGlobalScopes()` unless the model was JUST created in the same call (where the scope's `creating()` hook only needs context if `school_id` is blank and is otherwise a non-issue). Same rule applies to test assertions querying these models directly.

## Larastan nullsafe (?->) on a nullable BelongsTo can be a false "always non-null"
Larastan sometimes infers a nullable BelongsTo relation's magic property as never-null (flagging `$model->relation?->prop` as "nullsafe unnecessary") even when the FK column is genuinely nullable and the relation returns null at runtime for real rows. Don't blindly follow PHPStan's suggestion and remove the nullsafe — that introduces a real null-pointer bug. Instead rewrite as an explicit `$model->relation !== null ? $model->relation->prop : $default` ternary, which Larastan does not flag, and which preserves the real runtime null-safety. Also: PHPStan can correctly prove a match/if-chain over a regex capture group value is exhaustive after N-1 branches (e.g. an operator regex `(==|!=|>=|<=|>|<)?`) and flag the last explicit branch as dead code — that one IS usually a real, valid finding (not a false positive) and should be fixed by removing the last redundant branch and returning its expression directly as the final fallback.
