<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Boarding\Domain\Actions\AllocateBedAction;
use Modules\Boarding\Domain\DataObjects\AllocateBedData;
use Modules\Boarding\Domain\Exceptions\GenderMismatchException;
use Modules\Boarding\Livewire\Allocation\Board;
use Modules\Boarding\Livewire\Hostels\Structure;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelBed;
use Modules\Boarding\Models\HostelRoom;
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
 * Book F BRD-01 admin UI — Hostel, Room & Bed Allocation. Own,
 * distinctly-named fixture — see `StaffAdminUiTest`'s own note on why
 * a Pest helper defined in one test file can't be relied on from
 * another run standalone.
 *
 * @return array{school: School, year: AcademicYear, term: Term, hostel: Hostel, room: HostelRoom, bed: HostelBed, user: User}
 */
function hostelsAdminFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $hostel = Hostel::factory()->create(['school_id' => $school->id, 'gender' => 'female']);
    $room = HostelRoom::factory()->create(['school_id' => $school->id, 'hostel_id' => $hostel->id]);
    $bed = HostelBed::factory()->create(['school_id' => $school->id, 'room_id' => $room->id]);

    return ['school' => $school, 'year' => $year, 'term' => $term, 'hostel' => $hostel, 'room' => $room, 'bed' => $bed, 'user' => User::factory()->create()];
}

/**
 * @param  array<string, mixed>  $f
 */
function hostelsAdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $parts = explode('.', $permissionName);
        $moduleCode = $parts[0];
        $action = array_pop($parts);
        $resource = implode('.', array_slice($parts, 1));

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

it('serves every BRD-01 screen through a real routed request', function (): void {
    $f = hostelsAdminFixture();
    $user = hostelsAdminUser($f, 'boarding.hostel.view', 'boarding.hostel.manage', 'boarding.allocation.view', 'boarding.allocation.manage', 'boarding.incompatibility.manage', 'boarding.inspection.view', 'boarding.damage.view');

    foreach ([
        'boarding.hostels.structure',
        'boarding.allocation.board',
        'boarding.allocation.run',
        'boarding.allocation.waitlist',
        'boarding.allocation.constraints',
        'boarding.allocation.incompatibilities',
        'boarding.inspections.index',
        'boarding.damages.index',
    ] as $routeName) {
        $this->actingAs($user)->get(route($routeName, $f['school']))->assertOk();
    }

    $this->actingAs($user)->get(route('boarding.hostels.show', [$f['school'], $f['hostel']]))->assertOk();
});

it('refuses Hostels\\Structure to a user without boarding.hostel.view', function (): void {
    $f = hostelsAdminFixture();
    $user = hostelsAdminUser($f);

    Livewire::actingAs($user)->test(Structure::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('refuses allocation to a hostel of the wrong gender with no override, anywhere', function (): void {
    $f = hostelsAdminFixture();
    $student = Student::factory()->for($f['school'])->boarder()->create(['gender' => 'male']);

    // The fixture hostel is female-only; a male learner must be refused
    // unconditionally at the Action layer (AC-BRD-01-001) — there is no
    // permission, flag, or parameter on this call that can bypass it.
    expect(fn () => app(AllocateBedAction::class)->execute(new AllocateBedData(
        studentId: $student->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        effectiveFrom: Carbon::now(),
        allocatedByUserId: $f['user']->id,
        candidateHostelIds: [$f['hostel']->id],
        asDraft: false,
    )))->toThrow(GenderMismatchException::class);

    expect(BedAllocation::where('student_id', $student->id)->exists())->toBeFalse();
});

it('shows the real server-side gender refusal on the Allocation\\Board screen, with no bypass control', function (): void {
    $f = hostelsAdminFixture();
    $user = hostelsAdminUser($f, 'boarding.allocation.view', 'boarding.allocation.manage');
    $student = Student::factory()->for($f['school'])->boarder()->create(['gender' => 'male']);

    $component = Livewire::actingAs($user)->test(Board::class, ['school' => $f['school']])
        ->set('hostelId', $f['hostel']->id)
        ->set('allocateStudentId', $student->id)
        ->call('allocate');

    $component->assertDispatched('toast', variant: 'danger');
    expect(BedAllocation::where('student_id', $student->id)->exists())->toBeFalse();
});

it('creates a hostel through the admin screen and recomputes capacity only from real beds', function (): void {
    $f = hostelsAdminFixture();
    $user = hostelsAdminUser($f, 'boarding.hostel.view', 'boarding.hostel.manage');

    Livewire::actingAs($user)->test(Structure::class, ['school' => $f['school']])
        ->set('hostelCode', 'LOB')
        ->set('hostelName', 'Lobengula House')
        ->set('hostelGender', 'male')
        ->call('createHostel');

    $created = Hostel::where('school_id', $f['school']->id)->where('code', 'LOB')->first();

    expect($created)->not->toBeNull()
        ->and($created->capacity)->toBe(0);
});
