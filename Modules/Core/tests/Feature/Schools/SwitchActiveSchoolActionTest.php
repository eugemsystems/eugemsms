<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Schools\SwitchActiveSchoolAction;
use Modules\Core\Domain\DataObjects\Schools\SwitchSchoolData;
use Modules\Core\Domain\Exceptions\UnauthorisedSchoolAccessException;
use Modules\Core\Domain\Support\ActiveSchoolResolver;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Core\Models\UserSessionPreference;

it('records the target school\'s current year and term in the user\'s session preference', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $user = User::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);

    $result = app(SwitchActiveSchoolAction::class)->execute(new SwitchSchoolData($user->id, $school->id));

    expect($result->id)->toBe($school->id);

    $preference = UserSessionPreference::where('user_id', $user->id)->where('school_id', $school->id)->sole();
    expect($preference->academic_year_id)->toBe($year->id)
        ->and($preference->term_id)->toBe($term->id);
});

it('refuses to switch to a school the user is not assigned to (BR-CORE-02-009)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    app(SwitchActiveSchoolAction::class)->execute(new SwitchSchoolData($user->id, $school->id));
})->throws(UnauthorisedSchoolAccessException::class);

it('touches an existing preference row on a repeat switch instead of duplicating it', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);

    app(SwitchActiveSchoolAction::class)->execute(new SwitchSchoolData($user->id, $school->id));
    app(SwitchActiveSchoolAction::class)->execute(new SwitchSchoolData($user->id, $school->id));

    expect(UserSessionPreference::where('user_id', $user->id)->where('school_id', $school->id)->count())->toBe(1);
});

/**
 * 2026-09-12, user-reported, reproduced against real production-like
 * data after five rounds of "it's still not switching": `updateOrCreate()`
 * silently drops `updated_at` (not `$fillable` on `UserSessionPreference`),
 * and `Model::save()` only calls `updateTimestamps()` when the model is
 * ALREADY dirty from some other changed attribute. Switching to a school
 * whose computed current year/term happen to already match what's
 * stored — which is the common case for a school you'd switched to
 * before and nothing has changed since — left `updated_at` stale, so
 * `ActiveSchoolResolver::resolveId()` (picks the most recent by that
 * column) never recognised the switch. Reproduced here exactly: switch
 * to the SAME school twice in a row, so the second call's computed
 * values are identical to the first call's — the real failure mode, not
 * a contrived one.
 */
it("bumps the preference's updated_at even when the computed year/term are unchanged from what's already stored", function (): void {
    $school = School::factory()->create();
    AcademicYear::factory()->for($school)->current()->create();
    $user = User::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);

    app(SwitchActiveSchoolAction::class)->execute(new SwitchSchoolData($user->id, $school->id));
    $firstUpdatedAt = UserSessionPreference::where('user_id', $user->id)->where('school_id', $school->id)->sole()->updated_at;

    $this->travel(1)->minutes();

    app(SwitchActiveSchoolAction::class)->execute(new SwitchSchoolData($user->id, $school->id));
    $secondUpdatedAt = UserSessionPreference::where('user_id', $user->id)->where('school_id', $school->id)->sole()->updated_at;

    expect($secondUpdatedAt->greaterThan($firstUpdatedAt))->toBeTrue();
});

it('resolves the correct most-recently-switched-to school even when re-switching to one whose preference is unchanged', function (): void {
    $user = User::factory()->create();
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    AcademicYear::factory()->for($schoolA)->current()->create();
    AcademicYear::factory()->for($schoolB)->current()->create();
    $user->schools()->attach($schoolA, ['is_primary' => true, 'status' => 'active']);
    $user->schools()->attach($schoolB, ['is_primary' => false, 'status' => 'active']);

    // Establish A's preference row, then B's — B is now the most recent.
    app(SwitchActiveSchoolAction::class)->execute(new SwitchSchoolData($user->id, $schoolA->id));
    $this->travel(1)->minutes();
    app(SwitchActiveSchoolAction::class)->execute(new SwitchSchoolData($user->id, $schoolB->id));

    // Switch back to A. Its computed year/term are identical to what
    // was stored the first time — the exact scenario that silently
    // failed to register as "more recent than B" before this fix.
    $this->travel(1)->minutes();
    app(SwitchActiveSchoolAction::class)->execute(new SwitchSchoolData($user->id, $schoolA->id));

    expect(ActiveSchoolResolver::resolveId($user))->toBe($schoolA->id);
});
