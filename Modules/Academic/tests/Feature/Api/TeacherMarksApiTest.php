<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentMark;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * The teacher's mark entry over `/api/v1`: reach by creator or taught subject, per-learner results,
 * range checks, and submission locking further entry.
 *
 * @return array{school: School, teacher: User, mine: Assessment, theirs: Assessment, pupils: list<Student>}
 */
function marksApiFixture(PermissionScope $scope = PermissionScope::Assigned): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $teacher = User::factory()->create(['tenant_id' => $school->tenant_id]);
    $teacher->schools()->attach($school, ['status' => 'active', 'is_primary' => true]);
    $permission = Permission::firstOrCreate(['name' => 'academic.result.enter'], ['guard_name' => 'web', 'module_code' => 'ACADEMIC', 'resource' => 'result', 'action' => 'enter']);
    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(userId: $teacher->id, schoolId: $school->id, grants: [new PermissionGrantData($permission->id, $scope)]));

    $subject = Subject::factory()->for($school)->create();
    $other = Subject::factory()->for($school)->create();
    $mine = Assessment::factory()->create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'subject_id' => $subject->id, 'created_by' => $teacher->id, 'max_mark' => 50, 'title' => 'Test A']);
    $theirs = Assessment::factory()->create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'subject_id' => $other->id, 'title' => 'Test B']);
    $pupils = [];

    foreach (['Banda', 'Chuma'] as $surname) {
        $pupil = Student::factory()->for($school)->create(['last_name' => $surname]);
        LearnerSubjectEnrolment::factory()->create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'student_id' => $pupil->id, 'subject_id' => $subject->id]);
        $pupils[] = $pupil;
    }

    return compact('school', 'teacher', 'mine', 'theirs', 'pupils');
}

it('lists only the assessments the teacher created or teaches, and everything for section reach', function (): void {
    $f = marksApiFixture();
    Sanctum::actingAs($f['teacher'], ['*']);

    expect(collect($this->getJson('/api/v1/teacher/assessments')->assertOk()->json('data'))->pluck('title')->all())->toBe(['Test A']);
    $this->getJson('/api/v1/teacher/assessments/'.$f['theirs']->ulid)->assertNotFound();

    $wide = marksApiFixture(PermissionScope::School);
    Sanctum::actingAs($wide['teacher'], ['*']);
    expect(collect($this->getJson('/api/v1/teacher/assessments')->json('data'))->pluck('title')->sort()->values()->all())->toBe(['Test A', 'Test B']);
});

it('refuses a user without the mark-entry permission', function (): void {
    $f = marksApiFixture();
    $nobody = User::factory()->create(['tenant_id' => $f['school']->tenant_id]);
    $nobody->schools()->attach($f['school'], ['status' => 'active', 'is_primary' => true]);
    Sanctum::actingAs($nobody, ['*']);

    $this->getJson('/api/v1/teacher/assessments')->assertForbidden();
});

it('saves marks per learner, reporting an out-of-range or foreign learner without losing the rest, and overwriting on replay', function (): void {
    $f = marksApiFixture();
    Sanctum::actingAs($f['teacher'], ['*']);
    $stranger = Student::factory()->for($f['school'])->create();
    $url = '/api/v1/teacher/assessments/'.$f['mine']->ulid.'/marks';

    $results = $this->postJson($url, ['marks' => [
        ['student' => $f['pupils'][0]->ulid, 'raw_mark' => 40],
        ['student' => $f['pupils'][1]->ulid, 'raw_mark' => 75],
        ['student' => $stranger->ulid, 'raw_mark' => 10],
    ]], ['Idempotency-Key' => 'm-1'])->assertOk()->json('data.results');

    expect(collect($results)->pluck('saved')->all())->toBe([true, false, false])
        ->and(AssessmentMark::count())->toBe(1);

    $this->postJson($url, ['marks' => [['student' => $f['pupils'][0]->ulid, 'raw_mark' => 45], ['student' => $f['pupils'][1]->ulid, 'is_absent' => true]]], ['Idempotency-Key' => 'm-2'])->assertOk();
    $shown = collect($this->getJson('/api/v1/teacher/assessments/'.$f['mine']->ulid)->json('data.learners'))->keyBy('last_name');
    expect((float) $shown['Banda']['raw_mark'])->toBe(45.0)->and($shown['Chuma']['is_absent'])->toBeTrue();
});

it('submits the assessment, after which it no longer takes marks from the app', function (): void {
    $f = marksApiFixture();
    Sanctum::actingAs($f['teacher'], ['*']);

    $this->postJson('/api/v1/teacher/assessments/'.$f['mine']->ulid.'/submit', [], ['Idempotency-Key' => 'm-3'])->assertOk()->assertJsonPath('data.status', 'submitted');

    expect($f['mine']->fresh()->status)->toBe('submitted');
    $this->getJson('/api/v1/teacher/assessments/'.$f['mine']->ulid)->assertNotFound();
    $this->postJson('/api/v1/teacher/assessments/'.$f['mine']->ulid.'/marks', ['marks' => [['student' => $f['pupils'][0]->ulid, 'raw_mark' => 10]]], ['Idempotency-Key' => 'm-4'])->assertNotFound();
});
