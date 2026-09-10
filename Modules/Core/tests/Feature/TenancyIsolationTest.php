<?php

use Modules\Core\Domain\Exceptions\MissingSchoolContextException;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolModule;

/**
 * "Generated from the model registry — adding a model without a passing
 * isolation test fails CI" (Book A Part 1.11). Every module registers its
 * BelongsToSchool models into TenantModelRegistry (see
 * CoreServiceProvider::registerTenantModels()); this suite loops the
 * registry and proves cross-school isolation for each one.
 */
it('has at least one tenant model registered for the generator to cover', function (): void {
    expect(TenantModelRegistry::all())->not->toBeEmpty();
});

// A `->with(fn () => ...)` dataset built from TenantModelRegistry::all()
// resolves before the Laravel app (and so CoreServiceProvider::boot(),
// which populates the registry) exists — PHPUnit collects data providers
// while building the suite, ahead of any test's setUp(). Looping inside
// one test body avoids that ordering trap entirely: setUp() has always
// run by then, so the registry is always fully populated.
it('isolates every registered tenant model between two schools', function (): void {
    expect(TenantModelRegistry::all())->not->toBeEmpty();

    foreach (TenantModelRegistry::all() as $modelClass => $createForSchool) {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        SchoolContext::set($schoolA);
        $recordA = $createForSchool($schoolA);

        SchoolContext::set($schoolB);
        $recordB = $createForSchool($schoolB);

        SchoolContext::set($schoolA);
        $visibleToA = $modelClass::query()->pluck('id')->all();
        expect($visibleToA)->toContain($recordA->id)
            ->and($visibleToA)->not->toContain($recordB->id);
        expect($modelClass::find($recordB->id))->toBeNull("school A resolved school B's [{$modelClass}] row by id");

        SchoolContext::set($schoolB);
        $visibleToB = $modelClass::query()->pluck('id')->all();
        expect($visibleToB)->toContain($recordB->id)
            ->and($visibleToB)->not->toContain($recordA->id);
        expect($modelClass::find($recordA->id))->toBeNull("school B resolved school A's [{$modelClass}] row by id");
    }
});

it('returns nothing when no school context is set', function (): void {
    $school = School::factory()->create();
    SchoolContext::set($school);
    SchoolModule::factory()->for($school)->create();

    SchoolContext::clear();

    expect(SchoolModule::query()->count())->toBe(0);
});

it('refuses to create a tenant model with no resolvable school context', function (): void {
    SchoolContext::clear();

    // Constructed directly (not via the factory, whose school_id => School::factory()
    // relationship would resolve a concrete school even under make()) so
    // school_id is genuinely unset.
    (new SchoolModule(['module_code' => 'FIN', 'is_enabled' => true]))->save();
})->throws(MissingSchoolContextException::class);

it('bypasses the scope explicitly via withoutSchoolScope for console/system use', function (): void {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();

    SchoolContext::set($schoolA);
    SchoolModule::factory()->for($schoolA)->create();

    SchoolContext::set($schoolB);
    SchoolModule::factory()->for($schoolB)->create();

    // No authenticated user (console-style context) — bypass is permitted.
    expect(SchoolModule::withoutSchoolScope()->count())->toBe(2);
});
