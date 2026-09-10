<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Install\SeedZimbabweBaselineAction;
use Modules\Core\Domain\DataObjects\Install\SeedPackData;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;

it('runs the calendar pack and creates the current and next academic years with three terms each', function (): void {
    $school = School::factory()->create();

    $result = (new SeedZimbabweBaselineAction)->execute(new SeedPackData(
        schoolId: $school->id,
        packs: ['calendar'],
    ));

    expect($result->ranPacks())->toBe(['calendar'])
        ->and(AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->count())->toBe(2);

    $current = AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->where('is_current', true)->sole();
    expect($current->terms()->withoutGlobalScopes()->count())->toBe(3)
        ->and($current->terms()->withoutGlobalScopes()->where('is_current', true)->count())->toBe(1);
});

it('silently skips packs whose owning module does not exist yet', function (): void {
    $school = School::factory()->create();

    $result = (new SeedZimbabweBaselineAction)->execute(new SeedPackData(
        schoolId: $school->id,
        packs: ['calendar', 'roles', 'coa'],
    ));

    // 'roles' became real in CORE-05 (RoleSeedPack) — only 'coa' (FIN-01,
    // not built yet) is still pending here.
    expect($result->ranPacks())->toBe(['calendar', 'roles']);
});

it('does not duplicate years when the pack runs twice', function (): void {
    $school = School::factory()->create();
    $action = new SeedZimbabweBaselineAction;

    $action->execute(new SeedPackData($school->id, ['calendar']));
    $action->execute(new SeedPackData($school->id, ['calendar']));

    expect(AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->count())->toBe(2);
});

/**
 * Regression test: by the wizard's Seed step, the installer's own admin
 * is already authenticated (the Administrator step logs them in), and
 * this user has no `core.system.bypass_school_scope` ability — no
 * permission system exists yet (CORE-05). The pack's internal dedup
 * check must not rely on the permission-gated `withoutSchoolScope()`
 * escape hatch, or every real install run breaks the moment it reaches
 * this step. See CalendarSeedPack::createYear().
 */
it('runs for an already-authenticated installer admin without an InsufficientScopeException', function (): void {
    $school = School::factory()->create();
    $admin = User::factory()->create();

    $this->actingAs($admin);

    $action = new SeedZimbabweBaselineAction;
    $action->execute(new SeedPackData($school->id, ['calendar']));
    $action->execute(new SeedPackData($school->id, ['calendar']));

    expect(AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->count())->toBe(2);
});
