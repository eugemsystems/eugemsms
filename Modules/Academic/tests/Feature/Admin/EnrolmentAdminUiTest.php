<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Academic\Livewire\Allocation\Bulk;
use Modules\Academic\Livewire\Enrolment\BillingCheck;
use Modules\Academic\Livewire\Enrolment\LearnerSubjects;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\House;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentTimelineEvent;

/**
 * Book D ACA-02 §6 ⭐ admin UI — Class, Stream & Subject Enrolment.
 * Own, distinctly-named fixture — see `StaffAdminUiTest`'s own note on
 * why a Pest helper defined in one test file can't be relied on from
 * another run standalone.
 *
 * @return array{school: School, year: AcademicYear, term: Term, framework: CurriculumFramework, section: SchoolSection, gradeLevel: GradeLevel, user: User}
 */
function enrolmentAdminFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $termStart = now()->subDays(10)->startOfDay();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create([
        'starts_on' => $termStart,
        'ends_on' => $termStart->copy()->addDays(90),
    ]);
    $framework = CurriculumFramework::factory()->for($school)->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $year->id,
    ));

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'framework' => $framework,
        'section' => $section, 'gradeLevel' => $gradeLevel, 'user' => User::factory()->create(),
    ];
}

/**
 * @param  array<string, mixed>  $f
 */
function enrolmentAdminStudent(array $f, string $enrolmentType = 'PART_TIME'): Student
{
    return app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        firstName: 'Rutendo',
        lastName: 'Marufu',
        dateOfBirth: now()->subYears(15),
        gender: 'female',
        enrolmentType: $enrolmentType,
        residency: 'DAY',
        sectionId: $f['section']->id,
        gradeLevelId: $f['gradeLevel']->id,
        entryCohortYear: (int) now()->year,
        createdByUserId: $f['user']->id,
        skipDuplicateCheck: true,
    ));
}

/**
 * @param  array<string, mixed>  $f
 */
function enrolmentAdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        [$moduleCode, $resource, $action] = explode('.', $permissionName);

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => strtoupper($moduleCode), 'resource' => $resource, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    if ($grants !== []) {
        app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
            userId: $user->id, schoolId: $f['school']->id, grants: $grants,
        ));
    }

    return $user;
}

it('serves Enrolment\\LearnerSubjects through a real routed request', function (): void {
    $f = enrolmentAdminFixture();
    $student = enrolmentAdminStudent($f);
    $user = enrolmentAdminUser($f, 'academic.enrolment.view');

    $this->actingAs($user)
        ->get(route('academic.enrolment.subjects', [$f['school'], $student]))
        ->assertOk();
});

it('serves every other Enrolment screen through a real routed request', function (): void {
    $f = enrolmentAdminFixture();
    $student = enrolmentAdminStudent($f);
    $user = enrolmentAdminUser($f, 'academic.allocation.manage', 'academic.group.view', 'academic.group.manage', 'academic.selection.submit', 'academic.selection.approve', 'academic.enrolment.view');

    $this->actingAs($user)->get(route('academic.allocation.classes', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.groups.index', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.groups.allocate', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.selection.form', [$f['school'], $student]))->assertOk();
    $this->actingAs($user)->get(route('academic.selection.approvals', $f['school']))->assertOk();
});

it('refuses Enrolment\\LearnerSubjects to a user without academic.enrolment.view', function (): void {
    $f = enrolmentAdminFixture();
    $student = enrolmentAdminStudent($f);
    $user = enrolmentAdminUser($f);

    Livewire::actingAs($user)->test(LearnerSubjects::class, ['school' => $f['school'], 'student' => $student])
        ->assertForbidden();
});

it('adds a subject to a learner through the enrolment screen', function (): void {
    $f = enrolmentAdminFixture();
    $student = enrolmentAdminStudent($f);
    $subject = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);
    $user = enrolmentAdminUser($f, 'academic.enrolment.view', 'academic.enrolment.manage');

    Livewire::actingAs($user)->test(LearnerSubjects::class, ['school' => $f['school'], 'student' => $student])
        ->set('addSubjectId', $subject->id)
        ->call('addSubject');

    expect(LearnerSubjectEnrolment::where('student_id', $student->id)->where('subject_id', $subject->id)->where('status', 'active')->exists())->toBeTrue();
});

it('surfaces a billing mismatch for a part-time learner whose add has not yet billed (AC-ACA-02-010)', function (): void {
    $f = enrolmentAdminFixture();
    $student = enrolmentAdminStudent($f, 'PART_TIME');
    $subject = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id]);
    $user = enrolmentAdminUser($f, 'academic.enrolment.view', 'academic.enrolment.manage');

    Livewire::actingAs($user)->test(LearnerSubjects::class, ['school' => $f['school'], 'student' => $student])
        ->set('addSubjectId', $subject->id)
        ->call('addSubject');

    // No FIN-02 listener is wired up in this test (no fee structure
    // exists), so the enrolment's own billing_status stays 'pending' —
    // exactly the mismatch the reconciliation screen exists to surface.
    $component = Livewire::actingAs($user)->test(BillingCheck::class, ['school' => $f['school']]);

    $rows = $component->instance()->render()->getData()['rows'];
    $row = $rows->first(fn (array $r): bool => $r['student']->id === $student->id);

    expect($row)->not->toBeNull()
        ->and($row['mismatch'])->toBeTrue()
        ->and($row['actualCount'])->toBe(1)
        ->and($row['billedCount'])->toBe(0);
});

it('places many learners in a class in one step, skipping the wrong grade level, the already-placed and whatever exceeds capacity', function (): void {
    $f = enrolmentAdminFixture();
    $user = enrolmentAdminUser($f, 'academic.allocation.manage');
    $class = SchoolClass::factory()->for($f['school'])->for($f['year'])->for($f['gradeLevel'])->create(['capacity' => 2]);
    $otherLevel = GradeLevel::factory()->for($f['school'])->for($f['section'], 'section')->create();
    $a = enrolmentAdminStudent($f, 'FULL_TIME');
    $b = enrolmentAdminStudent($f, 'FULL_TIME');
    $c = enrolmentAdminStudent($f, 'FULL_TIME');
    $wrong = enrolmentAdminStudent($f, 'FULL_TIME');
    DB::table('students')->where('id', $wrong->id)->update(['grade_level_id' => $otherLevel->id]);

    $screen = Livewire::actingAs($user)->test(Bulk::class, ['school' => $f['school']])
        ->set('gradeLevelId', $f['gradeLevel']->id)->call('selectAll')->set('classId', $class->id)->call('applyClass');

    $placed = ClassAllocation::where('class_id', $class->id)->where('status', 'confirmed')->count();
    expect($placed)->toBe(2)->and(collect($screen->get('skipped'))->pluck('reason')->all())->toContain('The class is full.');

    $screen->set('selected', [$a->id, $wrong->id])->call('applyClass');
    $reasons = collect($screen->get('skipped'))->pluck('reason');
    expect($reasons)->toContain('Already in this class.')->toContain('The learner\'s grade level is not this class\'s.');
    expect(ClassAllocation::where('class_id', $class->id)->where('status', 'confirmed')->count())->toBe(2);
});

it('places learners in a house with a timeline line, skipping those already there and refusing an inactive house', function (): void {
    $f = enrolmentAdminFixture();
    $user = enrolmentAdminUser($f, 'academic.allocation.manage');
    $house = House::factory()->for($f['school'])->create(['name' => 'Tiger', 'is_active' => true]);
    $retired = House::factory()->for($f['school'])->create(['is_active' => false]);
    $a = enrolmentAdminStudent($f, 'FULL_TIME');
    $b = enrolmentAdminStudent($f, 'FULL_TIME');
    $b->update(['house_id' => $house->id]);

    $screen = Livewire::actingAs($user)->test(Bulk::class, ['school' => $f['school']])
        ->set('selected', [$a->id, $b->id])->set('houseId', $house->id)->call('applyHouse');

    expect($a->fresh()->house_id)->toBe($house->id)
        ->and(collect($screen->get('skipped'))->pluck('reason')->all())->toBe(['Already in this house.'])
        ->and(StudentTimelineEvent::where('student_id', $a->id)->where('event_type', 'house_allocated')->exists())->toBeTrue();

    $screen->set('selected', [$a->id])->set('houseId', $retired->id)->call('applyHouse')->assertHasErrors(['houseId']);

    Livewire::actingAs(enrolmentAdminUser($f))->test(Bulk::class, ['school' => $f['school']])->assertForbidden();
});
