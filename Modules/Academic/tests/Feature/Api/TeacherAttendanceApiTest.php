<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\ClassAllocation;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * The teacher's register over `/api/v1`: reach by class, offline-safe marking and conflicts.
 *
 * @return array{school: School, teacher: User, class: SchoolClass, other: SchoolClass, pupils: list<Student>}
 */
function teacherApiFixture(PermissionScope $scope = PermissionScope::Assigned): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    Term::factory()->for($school)->for($year, 'academicYear')->current()->create(['starts_on' => now()->subMonth()->toDateString(), 'ends_on' => now()->addMonth()->toDateString()]);
    $teacher = User::factory()->create(['tenant_id' => $school->tenant_id]);
    $teacher->schools()->attach($school, ['status' => 'active', 'is_primary' => true]);
    $permission = Permission::firstOrCreate(['name' => 'academic.attendance.mark'], ['guard_name' => 'web', 'module_code' => 'ACADEMIC', 'resource' => 'attendance', 'action' => 'mark']);
    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(userId: $teacher->id, schoolId: $school->id, grants: [new PermissionGrantData($permission->id, $scope)]));

    $class = SchoolClass::factory()->for($school)->create(['class_teacher_id' => $teacher->id, 'name' => '3A']);
    $other = SchoolClass::factory()->for($school)->create(['name' => '4B']);
    $pupils = [];

    foreach (['Banda', 'Chuma'] as $surname) {
        $pupil = Student::factory()->for($school)->create(['last_name' => $surname]);
        ClassAllocation::unguarded(fn () => ClassAllocation::query()->create([
            'school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => Term::query()->value('id'), 'student_id' => $pupil->id,
            'class_id' => $class->id, 'allocation_type' => 'initial', 'status' => 'confirmed', 'effective_from' => now()->subMonth()->toDateString(), 'allocated_by' => $teacher->id,
        ]));
        $pupils[] = $pupil;
    }

    return compact('school', 'teacher', 'class', 'other', 'pupils');
}

it('lists only the classes the teacher is class teacher of, and everything for school reach', function (): void {
    $f = teacherApiFixture();
    Sanctum::actingAs($f['teacher'], ['*']);

    expect(collect($this->getJson('/api/v1/teacher/classes')->assertOk()->json('data'))->pluck('name')->all())->toBe(['3A']);

    $this->getJson('/api/v1/teacher/classes/'.$f['other']->ulid.'/attendance')->assertStatus(404);

    $wide = teacherApiFixture(PermissionScope::School);
    Sanctum::actingAs($wide['teacher'], ['*']);
    expect(collect($this->getJson('/api/v1/teacher/classes')->json('data'))->pluck('name')->sort()->values()->all())->toBe(['3A', '4B']);
});

it('refuses a user with no attendance permission', function (): void {
    $f = teacherApiFixture();
    $nobody = User::factory()->create(['tenant_id' => $f['school']->tenant_id]);
    $nobody->schools()->attach($f['school'], ['status' => 'active', 'is_primary' => true]);
    Sanctum::actingAs($nobody, ['*']);

    $this->getJson('/api/v1/teacher/classes')->assertStatus(403);
});

it('shows the register with the learners alphabetically and any mark already made', function (): void {
    $f = teacherApiFixture();
    Sanctum::actingAs($f['teacher'], ['*']);

    $data = $this->getJson('/api/v1/teacher/classes/'.$f['class']->ulid.'/attendance')->assertOk()->json('data');

    expect(collect($data['learners'])->pluck('last_name')->all())->toBe(['Banda', 'Chuma'])
        ->and(collect($data['learners'])->pluck('status')->filter()->all())->toBe([])->and($data['status'])->toBe('pending');
});

it('marks a register, and replaying the same queued request changes nothing', function (): void {
    $f = teacherApiFixture();
    Sanctum::actingAs($f['teacher'], ['*']);
    $body = ['date' => now()->toDateString(), 'records' => [
        ['student' => $f['pupils'][0]->ulid, 'status' => 'present', 'idempotency_key' => 'a-1'],
        ['student' => $f['pupils'][1]->ulid, 'status' => 'late', 'minutes_late' => 10, 'idempotency_key' => 'a-2'],
    ]];

    $first = $this->postJson('/api/v1/teacher/classes/'.$f['class']->ulid.'/attendance', $body)->assertOk();
    expect($first->json('data.session_status'))->toBe('completed')->and($first->json('data.conflicts'))->toBe([]);

    $replay = $this->postJson('/api/v1/teacher/classes/'.$f['class']->ulid.'/attendance', $body)->assertOk();
    expect($replay->json('data.conflicts'))->toBe([])->and(AttendanceRecord::count())->toBe(2);

    $shown = $this->getJson('/api/v1/teacher/classes/'.$f['class']->ulid.'/attendance')->json('data.learners');
    expect(collect($shown)->pluck('status')->all())->toBe(['present', 'late']);
});

it('reports a conflicting second mark instead of overwriting the first', function (): void {
    $f = teacherApiFixture();
    Sanctum::actingAs($f['teacher'], ['*']);
    $url = '/api/v1/teacher/classes/'.$f['class']->ulid.'/attendance';
    $mk = fn (string $status, string $key) => ['date' => now()->toDateString(), 'records' => [['student' => $f['pupils'][0]->ulid, 'status' => $status, 'idempotency_key' => $key]]];

    $this->postJson($url, $mk('present', 'k-1'))->assertOk();
    $conflict = $this->postJson($url, $mk('absent', 'k-2'))->assertOk()->json('data.conflicts');

    expect($conflict)->toHaveCount(1)->and($conflict[0]['existing_status'])->toBe('present')->and($conflict[0]['attempted_status'])->toBe('absent')
        ->and($conflict[0]['student'])->toBe($f['pupils'][0]->ulid)
        ->and(AttendanceRecord::first()->status)->toBe('present');
});

it('rejects bad input: a future date, a date outside the term, a bad status, a missing key and a learner not on the register', function (): void {
    $f = teacherApiFixture();
    $stranger = Student::factory()->for($f['school'])->create();
    Sanctum::actingAs($f['teacher'], ['*']);
    $url = '/api/v1/teacher/classes/'.$f['class']->ulid.'/attendance';
    $row = ['student' => $f['pupils'][0]->ulid, 'status' => 'present', 'idempotency_key' => 'x'];

    $this->postJson($url, ['date' => now()->addDay()->toDateString(), 'records' => [$row]])->assertStatus(422);
    $this->postJson($url, ['date' => now()->subYear()->toDateString(), 'records' => [$row]])->assertStatus(422);
    $this->postJson($url, ['date' => now()->toDateString(), 'records' => [['status' => 'maybe'] + $row]])->assertStatus(422);
    $this->postJson($url, ['date' => now()->toDateString(), 'records' => [['student' => $f['pupils'][0]->ulid, 'status' => 'present']]])->assertStatus(422);
    $this->postJson($url, ['date' => now()->toDateString(), 'records' => [['student' => $stranger->ulid] + $row]])->assertStatus(422);
    $this->postJson('/api/v1/teacher/classes/'.$f['other']->ulid.'/attendance', ['date' => now()->toDateString(), 'records' => [$row]])->assertStatus(404);

    expect(AttendanceRecord::count())->toBe(0);
});
