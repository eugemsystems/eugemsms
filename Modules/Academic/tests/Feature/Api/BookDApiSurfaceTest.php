<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Modules\Academic\Domain\Actions\EnrolSubjectAction;
use Modules\Academic\Domain\DataObjects\EnrolSubjectData;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\LevelSubjectOffering;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectGroup;
use Modules\Academic\Models\SubjectSelectionRule;
use Modules\Academic\Models\TermResult;
use Modules\Academic\Models\TermSubjectResult;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * Book D's own `/api/v1` surface (ACA-01 §6, ACA-02 §7, ACA-04 §6, ACA-05 §7) — the same
 * "not Book-specific" gap Book C's own pass already closed for itself. Own, distinctly-named
 * fixture — a Pest helper defined in one file can't be relied on from another run standalone, and
 * `guardianApiFixture()` lives in `Modules\People`'s own test directory.
 *
 * @return array{school: School, year: AcademicYear, term: Term, framework: CurriculumFramework, section: SchoolSection, gradeLevel: GradeLevel, class: SchoolClass, subject: Subject, user: User, guardianUser: User, child: Student, link: StudentGuardian}
 */
function bookDApiFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $termStart = now()->subDays(10)->startOfDay();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create([
        'starts_on' => $termStart, 'ends_on' => $termStart->copy()->addDays(90),
    ]);
    $framework = CurriculumFramework::factory()->for($school)->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $class = SchoolClass::factory()->for($school)->for($year)->for($gradeLevel)->create();
    $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
    $user = User::factory()->create();
    $user->schools()->attach($school, ['status' => 'active']);

    $guardianUser = User::factory()->create();
    $guardianUser->schools()->attach($school, ['status' => 'active']);
    $guardian = Guardian::factory()->for($school)->create(['user_id' => $guardianUser->id]);
    $child = Student::factory()->for($school)->create(['grade_level_id' => $gradeLevel->id]);
    $link = StudentGuardian::factory()->create([
        'school_id' => $school->id, 'student_id' => $child->id, 'guardian_id' => $guardian->id,
    ]);

    ClassAllocation::factory()->for($school)->create([
        'academic_year_id' => $year->id, 'term_id' => $term->id, 'student_id' => $child->id,
        'class_id' => $class->id, 'status' => 'confirmed', 'effective_from' => now()->subDays(5)->toDateString(),
        'effective_to' => null, 'allocated_by' => $user->id,
    ]);

    $markPermission = Permission::firstOrCreate(['name' => 'academic.attendance.mark'], ['guard_name' => 'web', 'module_code' => 'ACADEMIC', 'resource' => 'attendance', 'action' => 'mark']);
    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(userId: $user->id, schoolId: $school->id, grants: [new PermissionGrantData($markPermission->id, PermissionScope::School)]));

    return compact('school', 'year', 'term', 'framework', 'section', 'gradeLevel', 'class', 'subject', 'user', 'guardianUser', 'child', 'link');
}

// --- CurriculumController (ACA-01 §6) --------------------------------------------------------

it('lists active frameworks, subjects, subject groups and offerings', function (): void {
    $f = bookDApiFixture();
    $group = SubjectGroup::factory()->for($f['school'])->create();
    LevelSubjectOffering::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'grade_level_id' => $f['gradeLevel']->id, 'subject_id' => $f['subject']->id,
    ]);
    Sanctum::actingAs($f['user'], ['*']);

    $this->getJson('/api/v1/academic/frameworks')->assertOk()->assertJsonPath('data.0.code', $f['framework']->code);

    $this->getJson("/api/v1/academic/subjects?framework={$f['framework']->ulid}")->assertOk()
        ->assertJsonPath('data.0.id', $f['subject']->ulid);

    $this->getJson('/api/v1/academic/subject-groups')->assertOk()->assertJsonPath('data.0.id', $group->ulid);

    $this->getJson("/api/v1/academic/offerings?level={$f['gradeLevel']->ulid}")->assertOk()
        ->assertJsonPath('data.0.subject.id', $f['subject']->ulid);
});

it('lists active selection rules and validates a proposed selection for a linked learner', function (): void {
    $f = bookDApiFixture();
    $rule = SubjectSelectionRule::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);
    Sanctum::actingAs($f['guardianUser'], ['*']);

    $this->getJson('/api/v1/academic/selection-rules')->assertOk()->assertJsonPath('data.0.id', $rule->ulid);

    $this->postJson('/api/v1/academic/selection-rules/validate', [
        'student' => $f['child']->ulid, 'subject_ids' => [$f['subject']->ulid],
    ])->assertOk()->assertJsonPath('data.is_valid', true);
});

it('refuses to validate a selection for a learner the guardian is not linked to', function (): void {
    $f = bookDApiFixture();
    $stranger = Student::factory()->for($f['school'])->create();
    Sanctum::actingAs($f['guardianUser'], ['*']);

    $this->postJson('/api/v1/academic/selection-rules/validate', [
        'student' => $stranger->ulid, 'subject_ids' => [$f['subject']->ulid],
    ])->assertNotFound();
});

// --- StudentAcademicsController (ACA-02 §7/ACA-05 §7) ----------------------------------------

it("shows a linked learner's current subjects and dated subject history", function (): void {
    $f = bookDApiFixture();
    app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(
        studentId: $f['child']->id, subjectId: $f['subject']->id, termId: $f['term']->id, addedByUserId: $f['user']->id,
    ));
    Sanctum::actingAs($f['guardianUser'], ['*']);

    $this->getJson("/api/v1/students/{$f['child']->ulid}/subjects")->assertOk()
        ->assertJsonPath('data.0.subject.id', $f['subject']->ulid);

    $this->getJson("/api/v1/students/{$f['child']->ulid}/subject-history")->assertOk()
        ->assertJsonPath('data.0.change_type', 'added');
});

it("rolls up a linked learner's performance trend for one subject across terms", function (): void {
    $f = bookDApiFixture();
    $earlierTerm = Term::factory()->for($f['school'])->for($f['year'], 'academicYear')->create([
        'number' => 2, 'starts_on' => $f['term']->starts_on->copy()->subMonths(4), 'ends_on' => $f['term']->starts_on->copy()->subMonths(1),
    ]);

    TermSubjectResult::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'term_id' => $earlierTerm->id, 'student_id' => $f['child']->id,
        'subject_id' => $f['subject']->id, 'final_percent' => 55,
    ]);
    TermSubjectResult::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'student_id' => $f['child']->id,
        'subject_id' => $f['subject']->id, 'final_percent' => 70,
    ]);
    Sanctum::actingAs($f['guardianUser'], ['*']);

    $data = $this->getJson("/api/v1/students/{$f['child']->ulid}/performance-trend?subject={$f['subject']->ulid}")
        ->assertOk()->json('data');

    expect($data)->toHaveCount(2)
        ->and($data[0]['final_percent'])->toEqual('55.00')
        ->and($data[1]['final_percent'])->toEqual('70.00');
});

// --- LearnerSelfController (/me/subjects, /me/attendance, /me/results) ----------------------

it("serves a learner token's own subjects, attendance and age-gated results", function (): void {
    $f = bookDApiFixture();
    $learnerUser = User::factory()->create();
    $learnerUser->schools()->attach($f['school'], ['status' => 'active']);
    $f['child']->update(['user_id' => $learnerUser->id]);

    app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(
        studentId: $f['child']->id, subjectId: $f['subject']->id, termId: $f['term']->id, addedByUserId: $f['user']->id,
    ));

    app(SetSettingValueAction::class)->execute(new SetSettingValueData('academic.publish_to_learner_portal', SettingScope::School, $f['school']->id, false));
    Sanctum::actingAs($learnerUser, ['*']);

    $this->getJson('/api/v1/me/subjects')->assertOk()->assertJsonPath('data.0.subject.id', $f['subject']->ulid);
    $this->getJson('/api/v1/me/attendance')->assertOk();
    $this->getJson('/api/v1/me/results')->assertOk()->assertJsonCount(0, 'data');

    app(SetSettingValueAction::class)->execute(new SetSettingValueData('academic.publish_to_learner_portal', SettingScope::School, $f['school']->id, true));
    TermResult::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'student_id' => $f['child']->id,
        'class_id' => $f['class']->id, 'status' => 'published',
    ]);

    $this->getJson('/api/v1/me/results')->assertOk()->assertJsonCount(1, 'data');
});

// --- SubjectSelectionsController (ACA-02 §6/§7) ----------------------------------------------

it('submits, shows, previews the fee for, and approves a subject selection as the linked guardian', function (): void {
    $f = bookDApiFixture();
    Sanctum::actingAs($f['guardianUser'], ['*']);

    $preview = $this->postJson('/api/v1/academic/selections/preview-fee', [
        'student' => $f['child']->ulid, 'subject_ids' => [$f['subject']->ulid],
    ])->assertOk();
    expect($preview->json('data.amount_minor'))->toBeNull();

    $store = $this->postJson('/api/v1/academic/selections', [
        'student' => $f['child']->ulid, 'subject_ids' => [$f['subject']->ulid],
    ], ['Idempotency-Key' => 'selection-1'])->assertCreated();

    $selectionId = $store->json('data.id');
    expect($store->json('data.status'))->toBe('submitted');

    $this->getJson("/api/v1/academic/selections/{$selectionId}")->assertOk()->assertJsonPath('data.status', 'submitted');

    $this->postJson("/api/v1/academic/selections/{$selectionId}/approve", [], ['Idempotency-Key' => 'approve-1'])
        ->assertOk()->assertJsonPath('data.status', 'guardian_approved');
});

it('refuses to submit or view a selection for a learner the guardian is not linked to', function (): void {
    $f = bookDApiFixture();
    $stranger = Student::factory()->for($f['school'])->create();
    Sanctum::actingAs($f['guardianUser'], ['*']);

    $this->postJson('/api/v1/academic/selections', [
        'student' => $stranger->ulid, 'subject_ids' => [$f['subject']->ulid],
    ], ['Idempotency-Key' => 'selection-2'])->assertNotFound();
});

// --- TeacherAttendanceController::sync (ACA-04 §6) -------------------------------------------

it('drains an offline attendance queue across two classes in one call, reporting a bad batch without aborting the rest', function (): void {
    $f = bookDApiFixture();
    $otherClass = SchoolClass::factory()->for($f['school'])->for($f['year'])->for($f['gradeLevel'])->create();
    $otherStudent = Student::factory()->for($f['school'])->create(['grade_level_id' => $f['gradeLevel']->id]);
    ClassAllocation::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'student_id' => $otherStudent->id,
        'class_id' => $otherClass->id, 'status' => 'confirmed', 'effective_from' => now()->subDays(5)->toDateString(),
        'effective_to' => null, 'allocated_by' => $f['user']->id,
    ]);
    Sanctum::actingAs($f['user'], ['*']);

    $response = $this->postJson('/api/v1/attendance/sync', [
        'batches' => [
            [
                'class' => $f['class']->ulid,
                'date' => now()->toDateString(),
                'records' => [[
                    'student' => $f['child']->ulid, 'status' => 'present', 'idempotency_key' => 'sync-a-1',
                ]],
            ],
            [
                'class' => $otherClass->ulid,
                'date' => now()->subYears(5)->toDateString(),
                'records' => [[
                    'student' => $otherStudent->ulid, 'status' => 'present', 'idempotency_key' => 'sync-b-1',
                ]],
            ],
        ],
    ])->assertOk();

    $results = $response->json('data.results');

    expect($results)->toHaveCount(2)
        ->and($results[0]['session_status'])->toBe('completed')
        ->and($results[1]['error'])->not->toBeNull();
});
