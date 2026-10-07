---
paths:
  - 'Modules/Welfare/**'
---

# Welfare

## Book G admin-UI pass: what was built and deliberately deferred, by module

**Correction (2026-10-07):** both "deliberately not built" claims below are stale. A later
gap-closing pass added `RecordConsultationAction` (Tier 3, complaint/assessment/plan encrypted at
rest, a visiting practitioner must be named) wired into `Health\Record` — `Consultations` is
built. Separately, `OPS-07` (Book H2) has since built `Modules\Sport\Models\Fixture`, so the
sports-fixture clash check for detentions is no longer blocked on a missing table — see
`ScheduleDetentionAction`'s own corrected docblock for what's still actually open there (the
cross-module query itself, not the table).

**BRD-06** 🔒 (13 screens, `Livewire/Health/`): `Record` (folds
"Clinical record" + "Condition register" — Tier 3, gated through
`ResolveMedicalTierAction` itself, not the permission flag alone),
`CarePlan` (Tier 2 — its condition picker selects only `public_summary`,
never `name`/`diagnosis_notes`), `Alerts` (Tier 2 board, same
Tier-2-only field discipline, verified by a dedicated test that a
Tier 3 clinical detail never reaches it), `SickBay` (folds
"Observations"), `MedicationRound`, `Prescriptions`, `Consents`,
`Immunisations`, `Incidents`, `Referrals` (folds make → return →
charge), `Stock` (folds "Controlled register"), `Outbreak` (new,
aggregate-only, no Action backs it), `Screenings`. `Consultations` is
now built too — `RecordConsultationAction`, via `Health\Record` (see
the correction above).

**BRD-07** (14 screens, `Livewire/Behaviour/`, `Sanctions/`,
`Detentions/`, `Committee/`, `Appeals/`, `Leadership/`): `Behaviour\
{Categories,Record,Learner,Board,Review,Rules,Analytics}`, `Sanctions\
{Types,Index,Issue}`, `Detentions\Register`, `Committee\Hearing`,
`Appeals\Index`, `Leadership\Index`. `Learner` folds in "Conduct
grades". `Board`/`Review`/`Learner` all mask a safeguarding-paused
(`is_confidential`) record's category/points, showing only "under
review" (BR-BRD-07-018) — verified by a dedicated test.
**Deliberately not built**: a sports-fixture clash check for
detentions — `OPS-07` has since built `Fixture`, so this is now only
missing the cross-module query itself, not the table (see the
correction above).

**BRD-08** 🔒🔒 (9 screens, `Livewire/Safeguarding/`,
`Livewire/Counselling/`) — see the dedicated section below before
touching anything here.

## BRD-08 — read this whole section before changing anything

### Permissions are registered under three module codes, NOT `WELFARE`

`WelfareServiceProvider::registerPermissions()` calls
`PermissionRegistry::register('HEALTH', [...])`,
`PermissionRegistry::register('BEHAVIOUR', [...])`, and
`PermissionRegistry::register('SAFEGUARDING', [...])` — three separate
calls, not one `PermissionRegistry::register('WELFARE', [...])` the
way every other module in this codebase does it. This is deliberate,
not an oversight: the backend Actions built in the prior pass already
call `hasPermissionTo()` with literal, unprefixed strings copied
straight from the spec —
`ResolveMedicalTierAction::execute()` checks `'health.clinical.view'`,
`ViewSafeguardingCaseAction::execute()` checks
`'safeguarding.emergency_access'` — and `Role::givePermissionTo()`'s
own vendor-exclusion guard plus `SyncPermissionCatalogueAction`'s
Super-Admin auto-grant skip both match on a literal `safeguarding.`
prefix (`SyncPermissionCatalogueActionTest` confirms this by
registering under module code `SAFEGUARDING` to get permission name
`safeguarding.case.view`). `$name = strtolower($moduleCode).'.'.$path`
— registering under `WELFARE` would have produced
`welfare.health.clinical.view` / `welfare.safeguarding.emergency_access`,
silently never matching those Action-layer checks. Any new permission
this module needs must be added to one of the three existing
`PermissionRegistry::register()` calls, never a new fourth one unless
the spec introduces a genuinely new top-level namespace.

### The impersonation gap this pass found and closed

During an active impersonation session, `Auth::user()` resolves to the
**impersonated** user, not the impersonator — so an ordinary
`authorizePermission()`/`hasPermissionTo()` check, on its own, sees the
impersonated user's own legitimate permissions and passes. This is
fine for every other module (impersonation is itself audited
elsewhere, Book A CORE-05), but BR-BRD-08-001 requires impersonation to
be hard-blocked here *regardless* of the impersonated user's access.
Closing it needs the active session explicitly:
`Modules\Core\Domain\Support\ImpersonationContext::current()` (a
request-scoped singleton populated by `SetImpersonationContext`
middleware on every real web request, so no extra wiring is needed in
production — only in a test, which must call `ImpersonationContext::set($session)`
directly to simulate it, since `Livewire::test()` doesn't run
middleware).

Two patterns close this, by screen shape:
- `Safeguarding\CaseDetail`/`Safeguarding\Grants` (both operate on one
  specific `SafeguardingCase`): pass
  `ImpersonationContext::current()` straight into
  `ViewSafeguardingCaseAction::execute($user, $case, $activeSession, $ip, $userAgent)`
  — the Action's own first check handles vendor type AND active
  impersonation together, and logs/alerts correctly either way.
- Every other screen that touches a concern, a case-adjacent
  dashboard, or a counselling session with **no single case to hand
  the Action** (`Safeguarding\{Triage,Cases,Reviews,Audit}`,
  `Counselling\Diary`): use the
  `Modules\Welfare\Livewire\Safeguarding\Concerns\BlocksVendorAndImpersonation`
  trait's `abortIfVendorOrImpersonating()`, called first in `mount()`,
  before any query. It's a plain 403 with no case-specific audit row
  (there's no case to attribute it to) — still a hard block, just
  without the per-case alerting `ViewSafeguardingCaseAction` can do.

A screen vendor-type users structurally cannot reach even without
either of the above: `Role::givePermissionTo()` already refuses to let
any vendor-only role hold a `safeguarding.*` permission, so a plain
vendor login fails `authorizePermission()` outright. The gap this
section closes is specifically the impersonation case, where that
first-layer defence doesn't apply because the permission check runs as
the *impersonated* user.

### No delete control exists anywhere in this module, verified by a dedicated test

`SafeguardingAdminUiTest`'s own `it('exposes no delete action...')`
statically scans every file in `Livewire/Safeguarding/` and
`resources/views/safeguarding/` for delete-shaped strings. Any new
screen or view added to either directory is covered by that same scan
automatically — no action needed, but never add a delete control to
either directory, full stop; `safeguarding_concerns`/`safeguarding_cases`
have no deletion path in the model layer either (`InvalidStateTransitionException`
on `->delete()`), so a UI delete button would be dead code at best.

### Two judgment calls made under genuine uncertainty — read before extending either screen

**`Safeguarding\Triage` reads `SafeguardingConcern.description` directly**,
gated only by `safeguarding.lead`/`safeguarding.deputy_lead`. No
`ViewSafeguardingConcernAction` exists in the domain layer the way
`ViewSafeguardingCaseAction` exists for cases — there is no per-concern
grant model, and the spec's own §6 screen-access column names the role
directly for this screen ("Triage queue | safeguarding lead"), unlike
a case, where role is explicitly only ever candidacy. This was treated
as the sanctioned enforcement for a pre-case concern specifically,
not generalised to cases. If a future pass adds per-concern granularity,
revisit this gate; until then, do not loosen it further (e.g. do not
widen it to `safeguarding.case.view`, which is candidacy-only and would
be a real leak here).

**`Safeguarding\CaseDetail` treats its own entries/risk-assessments/
referrals as covered by the ONE case-level audited read**, rather than
logging one audit row per entry/assessment/referral shown. BR-BRD-08-005
says "every read of a case, entry, counselling note or concern" is
audited, but no `ViewCaseEntryAction` (or equivalent) exists to call
per row. This pass reads that rule as satisfied by the one
`case_read`/`grant:*`/`break_glass` row the page's single
`ViewSafeguardingCaseAction` call already produces. If this is ever
judged too loose, the fix is a dedicated per-row audit call inside
`CaseDetail::render()`'s own data-fetching, not a looser case-level
check.

### Test fixture gotcha: `hasPermissionTo()` throws, it doesn't return false, for a permission row that doesn't exist yet

Every BRD-08/06/07 Action that calls `hasPermissionTo('some.permission')`
directly (not through `authorizePermission()`/`PermissionScopeResolver`)
throws `Spatie\Permission\Exceptions\PermissionDoesNotExist` if that
exact permission row has never been created in the `permissions`
table — it does NOT behave like a normal false-returning check. In
production this is a non-issue (`SyncPermissionCatalogueAction` has
already populated the whole catalogue by the time anyone reaches a
screen), but every test fixture in this module's `tests/Feature/Admin/`
pre-creates the specific rows the Actions check directly
(`safeguarding.emergency_access`, `safeguarding.deputy_lead`,
`health.clinical.view`, `health.actionable.view`) via
`Permission::firstOrCreate()`/`Permission::factory()->create()` in the
fixture itself, matching the pre-existing `Brd08SafeguardingTest`'s own
fixture. A new test that grants a DIFFERENT permission than the one an
Action checks directly will hit this — pre-create the row even when
not granting it to anyone.

### Admin-UI test files, one per domain, in `tests/Feature/Admin/`

`HealthAdminUiTest` (BRD-06), `BehaviourAdminUiTest` (BRD-07),
`SafeguardingAdminUiTest` (BRD-08) — each with its own
distinctly-named fixture function (`healthAdminFixture`/
`behaviourAdminFixture`/`sgAdminFixture`), matching this codebase's
established one-fixture-per-file convention. `SafeguardingAdminUiTest`
is the one with the hard minimum: Super Admin refused, impersonation
refused, per-case grant isolation (case A grant does not leak into
case B), audit row asserted for all four of lead/granted/break-glass/
denied, and the no-delete static scan.
