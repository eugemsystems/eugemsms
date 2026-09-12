<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * Regression guard: `Livewire::test(SomeClass::class)` mounts a
 * component directly and never exercises real routing, so it stayed
 * blind to a bug where every `Route::livewire($uri, SomeClass::class)`
 * route backed by a class literally named `Index` 500'd on a genuine
 * HTTP request with `ComponentNotFoundException` — Livewire's implicit-
 * binding-substitution step re-derives a canonical name from the class
 * and strips the trailing `.index` segment, then fails to resolve that
 * shortened name back to a class without a registered Livewire class
 * location for the module (fixed via `Livewire::addLocation()` in
 * `CoreServiceProvider::boot()`). This test only needs a real HTTP GET,
 * not full coverage of each screen's own behaviour (see their own
 * Livewire-level tests for that).
 */
it('loads every Index-named full-page Livewire route via a real HTTP GET', function (): void {
    $user = User::factory()->create();
    $school = School::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);

    $this->actingAs($user)->get('/schools')->assertOk();
    $this->actingAs($user)->get("/schools/{$school->ulid}/houses")->assertOk();
    $this->actingAs($user)->get("/schools/{$school->ulid}/modules")->assertOk();
});

/**
 * 2026-09-12 bugfix, user-reported: editing a numbering series 404'd
 * with the correct ulid in the URL. Root cause: implicit route-model
 * binding resolves every route parameter in one pass over the URI in
 * order — `{school}` first, then a `BelongsToSchool` child parameter
 * like `{series}`/`{term}`. `BelongsToSchool`'s `SchoolScope` filters
 * to zero rows without an ambient `SchoolContext` (BR-GLOBAL-010), and
 * nothing had set one yet at that point — `InteractsWithSchool::
 * loadSchool()` only runs once Livewire's `mount()` executes, well
 * after routing already finished resolving every parameter, including
 * the child one, which failed first. Exactly the class of bug
 * `Livewire::test()` can never catch (it hands the component an
 * already-resolved model directly, bypassing routing entirely) — every
 * screen's own test suite passed while the real URL 404'd. Fixed via
 * `School::resolveRouteBinding()` setting `SchoolContext` as a side
 * effect of `{school}` itself resolving, so it's already in place
 * before Laravel binds any sibling parameter in the same route. These
 * assertions need a real HTTP GET, same reason as the test above.
 */
it('resolves a BelongsToSchool child route parameter alongside {school} via a real HTTP GET', function (): void {
    $user = User::factory()->create();
    $school = School::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    $series = app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id,
        documentType: 'receipt',
        pattern: '{SCHOOL}/{TYPE}/{SEQ:6}',
    ));

    $permission = Permission::firstOrCreate(
        ['name' => 'core.numbering.manage'],
        ['guard_name' => 'web', 'module_code' => 'CORE', 'resource' => 'numbering', 'action' => 'manage'],
    );
    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id,
        schoolId: $school->id,
        grants: [new PermissionGrantData($permission->id, PermissionScope::School)],
    ));

    $this->actingAs($user)->get("/schools/{$school->ulid}/sessions/terms/{$term->ulid}")->assertOk();
    $this->actingAs($user)->get("/schools/{$school->ulid}/numbering/{$series->ulid}/edit")->assertOk();
});
