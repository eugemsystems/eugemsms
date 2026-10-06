---
paths:
  - 'Modules/Boarding/**'
---

# Boarding

## Book F admin-UI pass: what was built and deliberately deferred, by module

**BRD-01** (9 screens, `Livewire/Hostels/`, `Allocation/`, `Inspections/`,
`Damages/`): `Hostels\{Structure,Show}`, `Allocation\{Board,Run,Waitlist,
Constraints,Incompatibilities}`, `Inspections\Index`, `Damages\Index`.
`Allocation\Board` is a plain occupied/free bed table per hostel, not the
spec's own drag-to-move visual grid (`ACA-03 Timetable\Editor`'s own
precedent for the same trade-off) — the real server-side constraint
check still runs on every submit via `AllocateBedAction`/`MoveLearnerAction`.
`Board` also folds in the spec's separate "Bed availability" report and
"Learner allocation" screen (move/end acts directly on the selected
bed's occupant). **Deliberately not built**: the live drag-and-drop
grid itself — no Action or client-side state exists for it, and
building one would be decorative without a corresponding "live
constraint check as you drag" backend hook, which doesn't exist either.

**BRD-02** ⭐ (8 screens, `Livewire/RollCall/`, `Movement/`, `Occupancy/`):
`RollCall\{Take,Board,Incidents,Incident,Escalation}`, `Movement\{Log,
Checkpoints}`, `Occupancy\Live`. `Escalation` folds in "Roll call
points" (the spec's own screen table has no separate points screen —
a point always references a profile). `Board` also stands in for "Roll
call history" via a date picker over the same table. **Deliberately
not built**: a live countdown timer client-side for the next escalation
step (the server computes "due" on each `AdvanceEscalationLadderAction`
call; no websocket/polling push exists to tick a clock in the browser)
— `Incidents`'s "Check ladder" button is the honest on-demand
stand-in until `AdvanceEscalationLadderAction`'s own scheduled-command
wiring exists (see that action's own docblock).

**BRD-03** ⭐ (11 screens, `Livewire/Exeats/`, `Gate/`, `Visitors/`):
`Exeats\{Index,Show,Approvals,Overdue,Types}`, `Gate\{Terminal,
Attempts}`, `Visitors\{Terminal,Log,Blacklist,VisitingDays}`.
`Exeats\Index` folds in the unbuilt guardian-portal request form
(staff-recorded via `request_source = phone_recorded`, matching
BR-BRD-03-001's own exception for staff-initiated requests). `Exeats\Types`
folds in "Exeat quotas" as a read-only roll-up — no Action pre-sets a
quota ahead of a request; `ExeatQuota` rows are created on-demand
inside `RequestExeatAction` itself via `firstOrCreate`. **Deliberately
not built**: a dedicated "pre-set a learner's quota" form (no Action
exists for it — the quota table is populated lazily, by design, per
`ExeatQuota`'s own factory/model shape).

**BRD-04** (5 screens, `Livewire/Catering/`): `MenuCycles` (folds
"Menu planner"), `Recipes`, `ServicePlan` ⭐ (folds "Requisition &
issue" — planning-only mode means there is no real stock to issue/
return beyond the required-quantity lines this screen already shows),
`ServingTerminal`, `Dietary`. **Deliberately not built**: Cost
analytics/Wastage report (no real costing data exists while `FIN-09`
is unbuilt — `NullStoreIssuanceProvider` (no longer bound; `StoresIssuanceProvider` is) returned unavailable, not
zero, and a trend screen over permanently-unavailable figures would be
decorative, not useful), `PublicMenu` (a learner/guardian portal
screen — `ACA-06`'s own precedent is to defer portal-facing screens,
not build an admin stand-in for a different audience), and a dedicated
meal-attendance-capture screen (`catering.meal_attendance_capture`
defaults off; `ServicePlan`'s own `close()` already covers the
default path via `actual_served`).

**BRD-05** (5 screens, `Livewire/Linen/`, `Laundry/`): `Linen\{Items,
Issue,Clearance}`, `Laundry\{Cycles,Missing}`. `Linen\Issue` folds in
"Learner items" (picking a learner already shows their full item
list) and hosts the report-then-approve damage/lost lifecycle inline,
mirroring `Damages\Index`'s own shape from BRD-01.

## Four small, gap-filling Actions this pass added — same precedent as ACA-01's own catalogue gap

`CreateHostelWingAction`, `CreateAllocationConstraintAction`,
`CreateLearnerIncompatibilityAction`, `CreateMovementCheckpointAction`.
In every case, verified directly (`grep -rn "HostelWing::create\|
AllocationConstraint::create\|LearnerIncompatibility::create\|
MovementCheckpoint::create" Modules/Boarding/Domain/Actions` returned
nothing before this pass): the model, migration, and factory all
existed, but no Action anywhere ever created a row — every existing
one came from `BoardingServiceProvider::registerTenantModels()`'s own
factory call for the tenancy-isolation-test generator. Each new Action
is a plain `Model::create()` wrapped in `$this->transaction()`, no
business logic beyond what the migration already enforces — mirroring
`CreateHostelRoomAction`'s own shape exactly. None has an `Update`
counterpart, matching this codebase's established create-only
precedent elsewhere (Staff, Guardians, Intakes, ACA-01's catalogue).

`CreateAllocationConstraintAction`'s own docblock is explicit about
why this is safe: `constraint_type = 'gender_match'` is never read from
`allocation_constraints` by the allocation engine — gender segregation
is enforced unconditionally inside `AllocateBedAction`/`MoveLearnerAction`/
`RunBulkAllocationAction` themselves (`GenderMismatchException`, no
override parameter anywhere). The admin screen's own dropdown
(`Allocation\Constraints`) does not even list `gender_match` as an
option, so there is no path — accidental or deliberate — through this
new Action to weaken that rule.

## The gender-segregation rule has no UI bypass, verified by a dedicated test

`HostelsAdminUiTest::it('refuses allocation to a hostel of the wrong
gender with no override, anywhere')` calls `AllocateBedAction` directly
with a male learner against the fixture's female-only hostel and
asserts `GenderMismatchException` is thrown and no `BedAllocation` row
is created. A second test
(`it('shows the real server-side gender refusal on the Allocation\\Board
screen, with no bypass control')`) drives the same refusal through the
actual `Allocation\Board` Livewire component — no form field, flag, or
permission on that screen can route around the exception; the screen's
`allocate()` method catches `DomainException` and toasts it, exactly
like every other domain refusal in this codebase (`PaperVault`'s own
`vet()`/`seal()`/`release()` pattern).

## A DTO used as a public Livewire property fails to hydrate — store scalars/arrays instead

`Linen\Clearance` originally held `public ?LinenClearanceResult $result
= null;` and set it from `CheckLinenClearanceAction`'s return value.
Every GET request rendered fine (first paint, no hydration needed),
but the moment a second Livewire request round-tripped (any
`wire:click` after the first `check()` call), Livewire's own synth
layer threw `"Property type not supported in Livewire for property:
[...]"` — a plain readonly DTO has no registered Livewire synthesizer
(unlike `Illuminate\Support\Collection`/`Carbon`, which Livewire ships
synths for). Fixed by splitting the result into plain public
properties (`bool $checked`, `bool $isClear`, `array $outstandingItemIds`)
set field-by-field in `check()`, never storing the DTO object itself on
the component. **Any new Livewire component must avoid storing a
custom readonly DTO/value-object as a public property** — extract its
scalar/array fields onto separate public properties instead, the same
way `RunBulkAllocationAction`'s own `AllocationOutcome` collection is
mapped to a plain array in `Allocation\Run` before being assigned to
`$lastOutcomes`, not stored as a `Collection<AllocationOutcome>` directly.

## Permissions are registered under module code `BOARDING`, and three resources carry an extra segment

`BoardingServiceProvider::registerPermissions()` registers paths via
`PermissionRegistry::register('BOARDING', [...])` — the synced name is
`strtolower('BOARDING') . '.' . $path`, i.e. every check carries a
`boarding.` prefix automatically. Most registered paths are the
conventional two-segment `resource.action` shape (`hostel.view` →
`boarding.hostel.view`), but the catering and linen permission paths
are **copied verbatim from the spec's own BRD-04/05 permission lists**,
which themselves already contain an extra dot (`catering.menu.view`,
`catering.dietary.manage`, `linen.manage`) — so their synced names are
four segments long (`boarding.catering.menu.view`) or three
(`boarding.linen.manage`), not the usual three/two. Every
`authorizePermission(...)` call in `Catering\**`/`Linen\**`/`Laundry\**`
already carries the matching full prefixed string — a new screen in
either area must copy the exact registered path from
`BoardingServiceProvider::registerPermissions()`, not assume the
two-segment pattern the rest of the module follows. A test helper that
destructures a permission name via `explode('.', $name)` into exactly
three parts (the pattern most other modules' admin-UI tests use) will
silently mis-populate `resource`/`action` metadata for these four
longer names — harmless for the permission *lookup* itself (which
matches on the full `name` string), but `.ai/rules/*.md` test helpers
elsewhere assuming exactly three segments should split on the *last*
dot for the action and treat everything before it as the resource,
the way `Modules/Boarding/tests/Feature/Admin/*.php`'s own fixture
helpers do, if they need to support this module's permission names too.

## `HostelRoom`/`HostelBed` factories resolve a lazy `Hostel`/`HostelRoom` factory as BOTH `school_id` and the owning FK — never call them bare

`HostelRoomFactory::definition()` does `$hostel = Hostel::factory(); return
['school_id' => $hostel, 'hostel_id' => $hostel, ...]` — assigning the
same lazy `Factory` instance to two different attribute keys. Laravel's
factory resolution calls `->create()->getKey()` on a `Factory` value
independently per attribute it's assigned to, so `'school_id' => $hostel`
resolves to the **newly created Hostel's own id**, not its `school_id`
— nonsensical if a test ever calls `HostelRoom::factory()->create()`
bare. `HostelBedFactory` has the identical shape one level down
(`$room` assigned to both `school_id` and `room_id`). Both factories
are only ever exercised correctly today through
`BoardingServiceProvider::registerTenantModels()`'s own callbacks and
this pass's own admin-UI tests, which **always** override both keys
explicitly: `HostelRoom::factory()->create(['school_id' => $school->id,
'hostel_id' => $hostel->id])`. Do the same in any new test — never call
`HostelRoom::factory()->create()` or `HostelBed::factory()->create()`
without both FK overrides, the same trap `TimetableSlotFactory`'s own
docblock (Book E) already diagnosed for a different pair of columns.

## `RecordDepartureAction`'s refusal outcome is `escalated`, not `refused`, whenever the checker escalates

`CollectionAuthorityChecker::refuse($reason, escalate: true)` is the
path taken by every refusal reason this pass's gate terminal exercises
(`no_right`, `court_restriction`) — `RecordDepartureAction` then writes
`'outcome' => $decision->released ? 'released' : ($decision->escalate ?
'escalated' : 'refused')`. A test asserting `collection_attempts.outcome`
for a `no_right`/`court_restriction` refusal must expect `'escalated'`,
not the more intuitive `'refused'` — `'refused'` only appears when
`escalate: false` is passed, which none of this pass's exercised paths
do. Assert `outcome !== 'released'` plus the specific `refusal_reason`
when the exact escalate/refused distinction isn't the point of the
test, the way `ExeatsAdminUiTest`'s own `no_right` test does.
