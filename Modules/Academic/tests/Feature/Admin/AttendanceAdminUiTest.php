<?php

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Academic\Domain\Actions\GenerateAttendanceRegisterExportAction;
use Modules\Academic\Domain\Actions\GenerateAttendanceSessionAction;
use Modules\Academic\Domain\Actions\MarkAttendanceAction;
use Modules\Academic\Domain\DataObjects\GenerateAttendanceRegisterExportData;
use Modules\Academic\Domain\DataObjects\GenerateAttendanceSessionData;
use Modules\Academic\Domain\DataObjects\MarkAttendanceData;
use Modules\Academic\Domain\DataObjects\MarkAttendanceRecordInput;
use Modules\Academic\Livewire\Attendance\Mark;
use Modules\Academic\Livewire\Attendance\Reports;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\ClassAllocation;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Models\Student;

/**
 * Book D ACA-04 §6 admin UI — Attendance. Own, distinctly-named
 * fixture — see `StaffAdminUiTest`'s own note on why a Pest helper
 * defined in one test file can't be relied on from another run
 * standalone.
 *
 * @return array{school: School, year: AcademicYear, term: Term, section: SchoolSection, gradeLevel: GradeLevel, class: SchoolClass, user: User}
 */
function attendanceAdminFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $class = SchoolClass::factory()->for($school)->for($year)->for($gradeLevel)->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $year->id,
    ));

    return [
        'school' => $school, 'year' => $year, 'term' => $term,
        'section' => $section, 'gradeLevel' => $gradeLevel, 'class' => $class, 'user' => User::factory()->create(),
    ];
}

/**
 * @param  array<string, mixed>  $f
 */
function attendanceAdminStudent(array $f): Student
{
    $student = app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        firstName: 'Tanaka',
        lastName: 'Ndlovu',
        dateOfBirth: now()->subYears(12),
        gender: 'male',
        enrolmentType: 'FULL_TIME',
        residency: 'DAY',
        sectionId: $f['section']->id,
        gradeLevelId: $f['gradeLevel']->id,
        entryCohortYear: (int) now()->year,
        createdByUserId: $f['user']->id,
        classId: $f['class']->id,
        skipDuplicateCheck: true,
    ));

    ClassAllocation::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id,
        'term_id' => $f['term']->id,
        'student_id' => $student->id,
        'class_id' => $f['class']->id,
        'status' => 'confirmed',
        'effective_from' => now()->subDays(5)->toDateString(),
        'effective_to' => null,
        'allocated_by' => $f['user']->id,
    ]);

    return $student;
}

/**
 * @param  array<string, mixed>  $f
 */
function attendanceAdminUser(array $f, string ...$permissionNames): User
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

it('serves Attendance\\Mark through a real routed request', function (): void {
    $f = attendanceAdminFixture();
    $user = attendanceAdminUser($f, 'academic.attendance.mark');

    $this->actingAs($user)
        ->get(route('academic.attendance.mark', $f['school']))
        ->assertOk();
});

it('serves every other Attendance screen through a real routed request', function (): void {
    $f = attendanceAdminFixture();
    $user = attendanceAdminUser($f, 'academic.attendance.view', 'academic.attendance.view_compliance', 'academic.attendance.manage');

    foreach (['academic.attendance.daily', 'academic.attendance.compliance', 'academic.attendance.chronic', 'academic.attendance.reason-codes', 'academic.attendance.reports'] as $routeName) {
        $this->actingAs($user)->get(route($routeName, $f['school']))->assertOk();
    }
});

it('refuses Attendance\\Mark to a user without academic.attendance.mark', function (): void {
    $f = attendanceAdminFixture();
    $user = attendanceAdminUser($f);

    Livewire::actingAs($user)->test(Mark::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('the first mark stands and a conflicting resubmission is recorded as a conflict, never silently overwritten (AC-ACA-04-005)', function (): void {
    $f = attendanceAdminFixture();
    $student = attendanceAdminStudent($f);
    $user = attendanceAdminUser($f, 'academic.attendance.mark');

    $component = Livewire::actingAs($user)->test(Mark::class, ['school' => $f['school']])
        ->set('classId', $f['class']->id)
        ->set("statuses.{$student->id}", 'present')
        ->call('save');

    expect(AttendanceRecord::where('student_id', $student->id)->where('status', 'present')->exists())->toBeTrue();

    // A second, conflicting submission for the same student/session —
    // the first mark (present) must stand, never silently overwritten.
    $component->set("statuses.{$student->id}", 'absent')
        ->call('save');

    expect(AttendanceRecord::where('student_id', $student->id)->first()->status)->toBe('present')
        ->and(AttendanceRecord::where('student_id', $student->id)->count())->toBe(1);
});

it('reports a class\'s attendance for a range, never counting an unmarked day as present, and exports the register as CSV', function (): void {
    $f = attendanceAdminFixture();
    $student = attendanceAdminStudent($f);
    $marker = attendanceAdminUser($f, 'academic.attendance.mark');
    $viewer = attendanceAdminUser($f, 'academic.attendance.view');

    foreach ([[3, 'present'], [2, 'absent'], [1, 'late']] as [$daysAgo, $status]) {
        $session = app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
            schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
            sessionDate: now()->subDays($daysAgo), mode: 'daily', classId: $f['class']->id,
        ));
        app(MarkAttendanceAction::class)->execute(new MarkAttendanceData($session->id, [new MarkAttendanceRecordInput($student->id, $status)], $marker->id));
    }

    $screen = Livewire::actingAs($viewer)->test(Reports::class, ['school' => $f['school']])
        ->set('from', now()->subDays(10)->toDateString())->set('to', now()->toDateString())->set('classId', $f['class']->id);
    $row = $screen->viewData('classReport')[0];

    expect($row['present'])->toBe(1)->and($row['late'])->toBe(1)->and($row['absent'])->toBe(1)->and($row['percent'])->toBe(66.7);

    $screen->call('export')->assertFileDownloaded();
    $csv = app(GenerateAttendanceRegisterExportAction::class)->execute(new GenerateAttendanceRegisterExportData($f['class']->id, now()->subDays(10), now()));
    $lines = explode("\n", trim($csv));

    expect($lines)->toHaveCount(2)->and($lines[0])->toContain('Admission no')->and($lines[1])->toContain('Ndlovu')->and($lines[1])->toEndWith('"1","1","1","0"');
    expect(fn () => app(GenerateAttendanceRegisterExportAction::class)->execute(new GenerateAttendanceRegisterExportData($f['class']->id, now(), now()->subDay())))->toThrow(ValidationException::class);
});

it('draws a learner heatmap with unmarked days blank and lists recent absences with whether the parent was told', function (): void {
    $f = attendanceAdminFixture();
    $student = attendanceAdminStudent($f);
    $marker = attendanceAdminUser($f, 'academic.attendance.mark');
    $viewer = attendanceAdminUser($f, 'academic.attendance.view');
    $day = now()->subDay();

    while ($day->isWeekend()) {
        $day = $day->subDay();
    }

    $session = app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, sessionDate: $day, mode: 'daily', classId: $f['class']->id,
    ));
    app(MarkAttendanceAction::class)->execute(new MarkAttendanceData($session->id, [new MarkAttendanceRecordInput($student->id, 'absent')], $marker->id));

    $screen = Livewire::actingAs($viewer)->test(Reports::class, ['school' => $f['school']])
        ->set('tab', 'heatmap')->set('from', now()->subDays(7)->toDateString())->set('to', now()->toDateString())->set('studentId', $student->id);
    $cells = collect($screen->viewData('heatmap')['weeks'])->flatten(1)->filter()->keyBy('date');

    expect($cells[$day->toDateString()]['status'])->toBe('absent')
        ->and($cells->pluck('status')->filter()->count())->toBe(1);

    $follow = Livewire::actingAs($viewer)->test(Reports::class, ['school' => $f['school']])->set('tab', 'followup');
    expect($follow->viewData('followUp'))->toHaveCount(1);

    Livewire::actingAs(attendanceAdminUser($f))->test(Reports::class, ['school' => $f['school']])->assertForbidden();
});
