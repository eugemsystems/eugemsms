<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Academic\Livewire\Timetable\Cover;
use Modules\Academic\Livewire\Timetable\Editor;
use Modules\Academic\Livewire\Timetable\Structures;
use Modules\Academic\Models\AttendanceSession;
use Modules\Academic\Models\LessonSubstitution;
use Modules\Academic\Models\PeriodSlot;
use Modules\Academic\Models\PeriodStructure;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * Book E ACA-03 admin UI — Timetable & Scheduling Engine. Own,
 * distinctly-named fixture — see `StaffAdminUiTest`'s own note on why a
 * Pest helper defined in one test file can't be relied on from another
 * run standalone.
 *
 * @return array{school: School, year: AcademicYear, term: Term, structure: PeriodStructure, user: User}
 */
function timetableAdminFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $structure = PeriodStructure::factory()->for($school)->create(['academic_year_id' => $year->id]);

    return ['school' => $school, 'year' => $year, 'term' => $term, 'structure' => $structure, 'user' => User::factory()->create()];
}

/**
 * @param  array<string, mixed>  $f
 */
function timetableAdminUser(array $f, string ...$permissionNames): User
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

/**
 * @param  array<string, mixed>  $f
 */
function timetableAdminTimetable(array $f): Timetable
{
    return Timetable::factory()->create([
        'school_id' => $f['school']->id,
        'academic_year_id' => $f['year']->id,
        'term_id' => $f['term']->id,
        'structure_id' => $f['structure']->id,
    ]);
}

it('serves every Timetable screen with no route parameter through a real routed request', function (): void {
    $f = timetableAdminFixture();
    $user = timetableAdminUser(
        $f,
        'academic.timetable.view', 'academic.timetable.manage', 'academic.timetable.generate', 'academic.timetable.cover_manage',
    );

    $this->actingAs($user)->get(route('academic.timetable.structures', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.timetable.venues', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.timetable.constraints', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.timetable.requirements', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.timetable.generate', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.timetable.clashes', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.timetable.views', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.timetable.cover', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.timetable.exceptions', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('academic.timetable.exam-planner', $f['school']))->assertOk();
});

it('serves Editor and Publish through a real routed request', function (): void {
    $f = timetableAdminFixture();
    $timetable = timetableAdminTimetable($f);
    $user = timetableAdminUser($f, 'academic.timetable.view');

    $this->actingAs($user)->get(route('academic.timetable.editor', [$f['school'], $timetable]))->assertOk();
    $this->actingAs($user)->get(route('academic.timetable.publish', [$f['school'], $timetable]))->assertOk();
});

it('refuses Structures to a user without academic.timetable.view', function (): void {
    $f = timetableAdminFixture();
    $user = timetableAdminUser($f);

    Livewire::actingAs($user)->test(Structures::class, ['school' => $f['school']])->assertForbidden();
});

it('refuses a manual slot placement that would double-book a teacher, naming the conflict (BR-ACA-03-007)', function (): void {
    $f = timetableAdminFixture();
    $timetable = timetableAdminTimetable($f);
    PeriodSlot::factory()->for($f['school'])->create([
        'structure_id' => $f['structure']->id, 'cycle_day' => 1, 'period_number' => 1,
    ]);
    $staff = Staff::factory()->for($f['school'])->create();
    $subjectOne = Subject::factory()->for($f['school'])->create();
    $subjectTwo = Subject::factory()->for($f['school'])->create();
    $user = timetableAdminUser($f, 'academic.timetable.view', 'academic.timetable.edit');

    $component = Livewire::actingAs($user)->test(Editor::class, ['school' => $f['school'], 'timetable' => $timetable])
        ->set('cycleDay', '1')->set('periodNumber', '1')->set('subjectId', $subjectOne->id)->set('staffId', $staff->id)
        ->call('addSlot');

    expect(TimetableSlot::where('timetable_id', $timetable->id)->count())->toBe(1);

    $component->set('subjectId', $subjectTwo->id)->set('staffId', $staff->id)->call('addSlot');

    // The second placement shares the same teacher in the same cycle day/period — refused, not force-fitted.
    expect(TimetableSlot::where('timetable_id', $timetable->id)->count())->toBe(1);
});

it('assigns substitute cover and updates the attendance session\'s staff_id (AC-ACA-03-007)', function (): void {
    $substitution = LessonSubstitution::factory()->create(['status' => 'pending']);
    $school = School::find($substitution->school_id);
    SchoolContext::set($school);

    $term = Term::find($substitution->term_id);
    $session = AttendanceSession::factory()->create([
        'school_id' => $substitution->school_id,
        'academic_year_id' => $term->academic_year_id,
        'term_id' => $substitution->term_id,
        'mode' => 'period',
        'timetable_slot_id' => $substitution->timetable_slot_id,
        'session_date' => $substitution->substitution_date->toDateString(),
        'staff_id' => $substitution->absent_staff_id,
    ]);

    $coverStaff = Staff::factory()->create(['school_id' => $substitution->school_id]);
    $f = ['school' => $school];
    $user = timetableAdminUser($f, 'academic.timetable.cover_manage');

    Livewire::actingAs($user)->test(Cover::class, ['school' => $school])
        ->set("selectedCover.{$substitution->id}", $coverStaff->id)
        ->call('assign', $substitution->id);

    expect(LessonSubstitution::find($substitution->id)->status)->toBe('assigned')
        ->and(LessonSubstitution::find($substitution->id)->cover_staff_id)->toBe($coverStaff->id)
        ->and(AttendanceSession::find($session->id)->staff_id)->toBe($coverStaff->id);
});
