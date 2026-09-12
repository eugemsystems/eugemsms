<?php

use App\Models\User;
use Modules\Core\Domain\Support\ActiveSessionResolver;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Core\Models\UserSessionPreference;

it('returns [null, null] for a guest', function (): void {
    expect(ActiveSessionResolver::resolve(null, 1))->toBe([null, null]);
});

it('returns [null, null] when no school is active', function (): void {
    $user = User::factory()->create();

    expect(ActiveSessionResolver::resolve($user, null))->toBe([null, null]);
});

it("falls back to the school's current year and term when there is no preference", function (): void {
    $user = User::factory()->create();
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();

    expect(ActiveSessionResolver::resolve($user, $school->id))->toBe([$year->id, $term->id]);
});

it("prefers the user's saved preference over the school's current term (BR-CORE-03-006)", function (): void {
    $user = User::factory()->create();
    $school = School::factory()->create();
    $currentYear = AcademicYear::factory()->for($school)->current()->create();
    Term::factory()->for($school)->for($currentYear, 'academicYear')->current()->create();

    $preferredYear = AcademicYear::factory()->for($school)->create();
    $preferredTerm = Term::factory()->for($school)->for($preferredYear, 'academicYear')->create();

    UserSessionPreference::create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'academic_year_id' => $preferredYear->id,
        'term_id' => $preferredTerm->id,
    ]);

    expect(ActiveSessionResolver::resolve($user, $school->id))->toBe([$preferredYear->id, $preferredTerm->id]);
});

it('returns [null, null] when the school has no current academic year and no preference', function (): void {
    $user = User::factory()->create();
    $school = School::factory()->create();

    expect(ActiveSessionResolver::resolve($user, $school->id))->toBe([null, null]);
});
