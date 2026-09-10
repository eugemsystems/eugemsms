<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Schools\SwitchActiveSchoolAction;
use Modules\Core\Domain\DataObjects\Schools\SwitchSchoolData;
use Modules\Core\Domain\Exceptions\UnauthorisedSchoolAccessException;
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
