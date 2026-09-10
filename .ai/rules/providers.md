---
paths:
  - 'Modules/*/Providers/*ServiceProvider.php'
---

# Providers

## loadRoutesFrom() in a module provider needs an explicit web middleware group
Unlike bootstrap/app.php's own withRouting() (which auto-applies the `web` group to routes/web.php), a module's `$this->loadRoutesFrom($path)` call just requires() the file — it adds no middleware at all. A route file loaded this way with no explicit group has no session/CSRF/cookie handling, so `auth`/`verified` middleware silently treats every request as a sessionless guest and redirects to login (which, having no sidebar, can look like "the whole nav disappeared"). Always wrap module route loading in `Route::middleware('web')->group(fn () => $this->loadRoutesFrom($path))` (or 'api' for API routes) — found and fixed in CoreServiceProvider::boot() for routes/web.php and routes/schools.php; apply the same pattern to every future module's route registration.

## Only register a TenantModelRegistry model if it actually has its own school_id
TenancyIsolationTest queries `$modelClass::query()->pluck('id')` under each school's SchoolContext and asserts no cross-school leakage. A model with no `school_id` column of its own (e.g. FeeStructureRule — reached only through its owning FeeStructure, matching the spec's literal schema) has no BelongsToSchool scope at all, so the query returns every school's rows unfiltered and the isolation test fails with a leaked id.

How to apply: before adding `TenantModelRegistry::register(SomeModel::class, ...)`, confirm the model actually `use`s `BelongsToSchool`. If it doesn't (a pure child row reached only through a parent), leave it out and add a one-line comment explaining why — same treatment Book A's Part 1.11 already gives `AccountBalance`/`SubledgerBalance` for a different reason (cache tables).

Also: when a factory closure passes `->for($school)` to override just the `school_id` column, any OTHER school-scoped model created inside that factory's own `definition()` (e.g. `FeeComponentFactory` building its own `income_account_id`/`debtor_account_id` Accounts from an internal `School::factory()`) stays tied to that internal, different school — `->for()` never rewrites nested factory defaults. Fix by passing the real IDs explicitly (see `FinanceServiceProvider::registerTenantModels()`'s `FeeComponent`/`AdHocCharge`/`BillingRun` registrations) rather than relying on `->for($school)` alone.

## Cross-module event wiring: Listener class lives in the consumer module, registered via Event::listen in its ServiceProvider::boot()
No `EventServiceProvider`/`$listen` map exists in any module — every module's events are wired, if at all, directly in that module's own `*ServiceProvider::boot()` via `Event::listen(EventClass::class, [ListenerClass::class, 'method'])`.

First instance: `Modules\Finance\Domain\Listeners\RaiseMidTermSubjectChangeBillingListener` reacts to Academic's `SubjectEnrolmentAdded`/`SubjectEnrolmentDropped`, registered in `FinanceServiceProvider::registerEventListeners()`. The listener lives in Finance (the consumer/downstream module), not Academic (the producer) — same dependency direction as the Action it calls (`BillMidTermSubjectChangeAction` already imports Academic models). Follow this direction for any future cross-module listener: it lives with whichever module already depends on the other, never the reverse.

The listener never lets a failure in the reacted-to side effect roll back the transaction that emitted the event (`event()` fires synchronously, inside the emitting Action's own open transaction) — wrap the call in try/catch and record success/failure on a tracking column instead of throwing, per CLAUDE.md's "never block the operation it attaches to" doctrine. Also check an idempotency flag (here `subject_enrolment_changes.billing_event_dispatched`) before acting, since a replayed event must not double-execute a financial side effect.
