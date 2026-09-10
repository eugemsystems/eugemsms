<?php

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Sessions\SwitchSessionAction;
use Modules\Core\Domain\DataObjects\Sessions\SwitchSessionData;
use Modules\Core\Domain\Exceptions\UnauthorisedSchoolAccessException;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Core\Models\UserSessionPreference;

it('switches to a session belonging to the user\'s assigned school (BR-CORE-03-006)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

    $result = app(SwitchSessionAction::class)->execute(new SwitchSessionData($user->id, $school->id, $year->id, $term->id));

    expect($result->academicYear->id)->toBe($year->id)->and($result->term?->id)->toBe($term->id);

    $preference = UserSessionPreference::where('user_id', $user->id)->where('school_id', $school->id)->sole();
    expect($preference->academic_year_id)->toBe($year->id)->and($preference->term_id)->toBe($term->id);
});

it('refuses a school the user is not assigned to', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();

    app(SwitchSessionAction::class)->execute(new SwitchSessionData($user->id, $school->id, $year->id));
})->throws(UnauthorisedSchoolAccessException::class);

it('refuses an academic year that does not belong to the claimed school', function (): void {
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    $user = User::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    $foreignYear = AcademicYear::factory()->for($otherSchool)->create();

    app(SwitchSessionAction::class)->execute(new SwitchSessionData($user->id, $school->id, $foreignYear->id));
})->throws(ValidationException::class);
