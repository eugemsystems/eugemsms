---
paths:
  - 'Modules/Compliance/**'
---

# Compliance

## Book H3 CMP-01–04 admin-UI pass: what was built, by module

All 50 backend Actions pre-existed this pass (a prior backend-only pass).
This pass added 22 Livewire screens, one `compliance.php` route file, and
four `tests/Feature/Admin/*.php` files — no new Actions, migrations, or
models.

**CMP-01** (`Livewire/Zimsec/`, 7 screens): `Registrations\Index`
(create/derive/close, deadline countdown computed inline rather than
calling `CheckZimsecDeadlinesAction`, which is a scheduled-scan action,
not a screen concern), `Validation\Index` (folds validation-rule
management into the same screen as candidate errors — fix-in-place
means adjusting the rule, never the candidate's bio-data, which is
never re-keyed per BR-CMP-01-001), `Fees\Index`, `Export\Index` (folds
`RecordZimsecSubmissionAction` in — the spec's own §1 note says
submission only happens "once the school has exported"), `Statements\Index`,
`ResultsImport\Index` (plain comma-separated textarea rows, matching
`Payroll\Statutory\Config`'s own raw-textarea-over-structured-upload
precedent for low-volume admin input), `Analysis\Index`.

**CMP-02** (`Livewire/Mopse/`, 2 screens — **no §4 screens table exists
in the spec for this module**, unlike CMP-01/03): `SchoolReturns\Index`
folds the whole generate → quality-check → export → submit lifecycle
onto one action-bar screen, matching `Reports\Close\Checklist`'s own
precedent; `InspectionPack\Index` is separate because it produces a
bare `File`, not a `statutory_school_returns` row.

**CMP-03** (`Livewire/Privacy/`, 9 screens, matching the spec's own §4
table exactly): `ConsentTypes`, `Consents`, `Retention`, `Disposal`,
`Requests`, `Breaches`, `Processing`, `Processors`, `Notices` — see the
dedicated section below before touching `Retention` or `Disposal`.

**CMP-04** (`Livewire/Policy/`, 4 screens — **no §4 screens table
exists in the spec for this module either**): `Policies\Index`,
`StatutoryDocuments\Index` (named this, not `Documents`, to read well
next to the `statutory_documents` table — nesting under `Policy\`
already prevents any collision with `Core\Livewire\Documents\Index`'s
own relative path regardless; also folds the contract register in,
since both tables share `CheckDocumentExpiryAction`'s one expiry
mechanism), `Minutes\Index`, `IncidentRegister\Index`.

## Permissions: four module codes, not one — read before adding a permission

`ComplianceServiceProvider::registerPermissions()` calls
`PermissionRegistry::register('ZIMSEC', [...])`,
`PermissionRegistry::register('MOPSE', [...])`,
`PermissionRegistry::register('PRIVACY', [...])`, and
`PermissionRegistry::register('POLICY', [...])` — four separate calls,
not one `PermissionRegistry::register('COMPLIANCE', [...])` the way a
single-Laravel-module pass might default to. This mirrors
`Modules\Welfare`'s own documented precedent (`.ai/rules/welfare.md`)
for the identical reason: `PermissionRegistry::register()` computes
`$name = strtolower($moduleCode).'.'.$path`, and CMP-01's/CMP-03's own
spec §4 screens tables name their permissions literally as
`zimsec.manage`/`privacy.view`/etc, not `compliance.zimsec.manage`.
Registering under `COMPLIANCE` would silently produce the wrong
permission name for every screen's `authorizePermission()` call.
CMP-02/CMP-04 have no spec-given names (no §4 table in either
section) — `MOPSE`/`POLICY` are this pass's own choice, kept
consistent with the other two for the same reason.

## A real Livewire gotcha this pass hit twice: a custom readonly DataObject (or a Collection of them) cannot be a public Livewire property

`AnalyseZimsecPassRatesAction` returns a `ZimsecPassRateResult`
readonly DataObject; `GenerateConsolidatedIncidentRegisterAction`
returns a `Collection` of `ConsolidatedIncidentRegisterEntry` readonly
DataObjects. Storing either directly as a public property on
`Zimsec\Analysis\Index` / `Policy\IncidentRegister\Index` throws
`Property type not supported in Livewire for property: [...]` the
moment Livewire tries to dehydrate the component after any `->call()`
— Livewire's synthesizer system has no built-in support for an
arbitrary plain PHP object, only Eloquent models, Carbon instances,
Collections of wireable items, and a handful of other built-ins. Both
components now convert the Action's result to a plain array
(`['bySubject' => ..., 'byClass' => ..., 'historical' => ...]`, and
`$collection->map(fn ($entry) => ['source' => $entry->source, ...])->all()`)
before assigning it to the public property, and both views index into
the array (`$result['bySubject']`, `$entry['source']`) rather than
accessing object properties. Before giving any new Compliance screen a
public property typed as one of this module's own
`Domain\DataObjects\*` classes (or a `Collection` of them), convert to
an array first — an Eloquent model (e.g. `?GovernanceMinute` on
`Policy\Minutes\Index`) is fine as-is, Livewire has native support for
those.

## CMP-03's `Retention`/`Disposal` screens — the safeguarding-content boundary

`retention_schedules.record_class` includes `safeguarding_record` in
the spec's own enum, and `disposal_queue` could in principle hold a
row pointing at a safeguarding case. `Privacy\Disposal\Index` and its
view show ONLY queue metadata — `record_type`, `record_id` (a bare
pointer, never resolved to an Eloquent model), `schedule->record_class`,
`eligible_on`, `review_status`, `deferred_until`/`deferral_reason`,
`disposed_at`, `disposal_method` — and never query, load, or render
the underlying record's own content. Neither screen's PHP nor its view
ever references `Modules\Welfare`, `SafeguardingCase`,
`SafeguardingConcern`, or `ViewSafeguardingCaseAction` — not even in a
docblock, since `Cmp03PrivacyAdminUiTest`'s own static-scan test
(`it('never exposes a safeguarding record's own content...')`, mirroring
`SafeguardingAdminUiTest`'s own no-delete scan in Book G) greps the raw
file contents for exactly those strings, comments included. If you
need to explain the Welfare module's role in a docblock, write around
the literal namespace string (e.g. "the Welfare module's own sanctioned
action", not `` `Modules\Welfare`'s `ViewSafeguardingCaseAction` ``) —
the scan caught this pass's own first draft doing exactly that.
`EnqueueDueDisposalsAction` only wires one concrete resolver today
(`application_unsuccessful`, declined `Application` rows) — no
safeguarding row can reach the queue in practice yet, but the display
discipline holds regardless, for whenever a `safeguarding_record`
resolver is wired.

## Routes and sidebar

One route file, `Modules/Compliance/routes/compliance.php`, loaded from
`ComplianceServiceProvider::registerLivewireRoutes()` the same way
every other Book H3 module's provider does it — `schools/{school}/compliance/{zimsec,mopse,privacy,policy}/...`,
route names `compliance.{zimsec,mopse,privacy,policy}.{screen}.index`.
Sidebar: a NEW top-level "Regulatory Compliance" group in
`resources/views/layouts/app/sidebar.blade.php` (`$complianceGroupActive`,
scoped to `compliance.*`), separate from the existing "Payroll &
Compliance" group (which covers PPL-05/FIN-13/FIN-14/FIN-12 only,
despite its name) — ZIMSEC/MoPSE/data protection/policy aren't payroll
or fiscalisation concerns, and the route prefixes don't overlap.

## Duplicate Livewire component name check

Every screen in this pass is nested two levels under `Livewire/`
(`Zimsec/Registrations/Index`, `Privacy/Disposal/Index`, etc.), so none
collides with another module's own relative path — run
`find . -path "*/Livewire/*" -name "*.php" | grep -v vendor | sed -E 's#.*/Livewire/##; s#\.php$##' | sort | uniq -d`
before adding a new screen regardless; it was run clean before this
pass and once more after.
