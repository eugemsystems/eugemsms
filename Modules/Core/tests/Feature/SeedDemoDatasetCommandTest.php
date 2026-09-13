<?php

use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\Term;

/**
 * `AcademicYear`/`Term` are `BelongsToSchool` — querying them here
 * (verifying data across all four seeded schools at once, not from
 * inside one school's own screen) needs `withoutGlobalScopes()`, the
 * same "no ambient SchoolContext means the scope returns zero rows,
 * not unfiltered" gotcha this session hit before (see
 * School::resolveRouteBinding()'s own history).
 */
it('seeds two tenants, four schools, and three full academic years per school (Book A Acceptance Gate)', function (): void {
    $this->artisan('serp:seed:demo-dataset')->assertSuccessful();

    expect(Tenant::count())->toBe(2)
        ->and(School::count())->toBe(4);

    foreach (School::all() as $school) {
        $years = AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->get();
        expect($years)->toHaveCount(3);

        foreach ($years as $year) {
            expect(Term::withoutGlobalScopes()->where('academic_year_id', $year->id)->count())->toBe(3);
        }
    }
});

it('is idempotent — running it again skips tenants that already exist', function (): void {
    $this->artisan('serp:seed:demo-dataset')->assertSuccessful();
    $this->artisan('serp:seed:demo-dataset')->assertSuccessful();

    expect(Tenant::count())->toBe(2)
        ->and(School::count())->toBe(4);
});
