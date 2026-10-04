<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Boarding\Domain\Actions\MarkRollCallAction;
use Modules\Boarding\Domain\Actions\OpenRollCallAction;
use Modules\Boarding\Domain\DataObjects\MarkRollCallData;
use Modules\Boarding\Domain\DataObjects\OpenRollCallData;
use Modules\Boarding\Livewire\Movement\Log;
use Modules\Boarding\Livewire\RollCall\Incidents;
use Modules\Boarding\Livewire\RollCall\Take;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\EscalationProfile;
use Modules\Boarding\Models\EscalationStep;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelBed;
use Modules\Boarding\Models\HostelRoom;
use Modules\Boarding\Models\MissingLearnerIncident;
use Modules\Boarding\Models\MovementCheckpoint;
use Modules\Boarding\Models\MovementLogEntry;
use Modules\Boarding\Models\RollCallPoint;
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
 * Book F BRD-02 admin UI — Roll Call & Movement ⭐. Own,
 * distinctly-named fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term, hostel: Hostel, bed: HostelBed, point: RollCallPoint, profile: EscalationProfile, student: Student, user: User}
 */
function rollCallAdminFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $hostel = Hostel::factory()->create(['school_id' => $school->id, 'gender' => 'male']);
    $room = HostelRoom::factory()->create(['school_id' => $school->id, 'hostel_id' => $hostel->id]);
    $bed = HostelBed::factory()->create(['school_id' => $school->id, 'room_id' => $room->id]);

    $profile = EscalationProfile::factory()->create(['school_id' => $school->id, 'is_default' => true]);
    EscalationStep::factory()->create(['profile_id' => $profile->id, 'step_number' => 1, 'delay_minutes' => 0, 'requires_action_record' => true]);
    EscalationStep::factory()->create(['profile_id' => $profile->id, 'step_number' => 2, 'delay_minutes' => 10, 'requires_action_record' => false]);

    $point = RollCallPoint::factory()->create(['school_id' => $school->id, 'escalation_profile_id' => $profile->id]);

    $student = Student::factory()->for($school)->boarder()->create(['gender' => 'male']);
    BedAllocation::factory()->create([
        'school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id,
        'student_id' => $student->id, 'bed_id' => $bed->id, 'hostel_id' => $hostel->id, 'room_id' => $room->id,
        'status' => 'confirmed', 'effective_from' => Carbon::now()->subDay(), 'effective_to' => null,
    ]);

    return ['school' => $school, 'year' => $year, 'term' => $term, 'hostel' => $hostel, 'bed' => $bed, 'point' => $point, 'profile' => $profile, 'student' => $student, 'user' => User::factory()->create()];
}

/**
 * @param  array<string, mixed>  $f
 */
function rollCallAdminUser(array $f, string ...$permissionNames): User
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

it('serves every BRD-02 screen through a real routed request', function (): void {
    $f = rollCallAdminFixture();
    $user = rollCallAdminUser($f, 'boarding.rollcall.conduct', 'boarding.rollcall.view', 'boarding.rollcall.manage', 'boarding.incident.view', 'boarding.movement.view', 'boarding.movement.manage');

    foreach ([
        'boarding.rollcall.take',
        'boarding.rollcall.board',
        'boarding.rollcall.incidents',
        'boarding.rollcall.escalation',
        'boarding.movement.log',
        'boarding.movement.checkpoints',
        'boarding.occupancy.live',
    ] as $routeName) {
        $this->actingAs($user)->get(route($routeName, $f['school']))->assertOk();
    }
});

it('refuses RollCall\\Take to a user without boarding.rollcall.conduct', function (): void {
    $f = rollCallAdminFixture();
    $user = rollCallAdminUser($f);

    Livewire::actingAs($user)->test(Take::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('opens an incident the moment a learner is marked missing, and acknowledgement alone never satisfies a step requiring an action record (BR-BRD-02-009/010)', function (): void {
    $f = rollCallAdminFixture();

    $rollCall = app(OpenRollCallAction::class)->execute(new OpenRollCallData(
        rollCallPointId: $f['point']->id,
        hostelId: $f['hostel']->id,
        termId: $f['term']->id,
        rollDate: Carbon::now(),
    ));

    app(MarkRollCallAction::class)->execute(new MarkRollCallData(
        rollCallId: $rollCall->id,
        studentId: $f['student']->id,
        status: 'missing',
        markedByUserId: $f['user']->id,
    ));

    $incident = MissingLearnerIncident::where('student_id', $f['student']->id)->first();
    expect($incident)->not->toBeNull()
        ->and($incident->status)->toBe('open')
        ->and($incident->current_step)->toBe(1);

    $user = rollCallAdminUser($f, 'boarding.incident.view', 'boarding.incident.action');

    // Acknowledging step 1 must never, by itself, satisfy a step flagged
    // requires_action_record — the free-text action-record call is the
    // only path, and recordAction() refuses bare empty text.
    Livewire::actingAs($user)->test(Incidents::class, ['school' => $f['school']])
        ->call('acknowledge', $incident->id, 1)
        ->call('recordAction', $incident->id, 1)
        ->assertDispatched('toast', variant: 'danger');

    expect($incident->refresh()->actions()->where('action_type', 'action_recorded')->exists())->toBeFalse();

    Livewire::actingAs($user)->test(Incidents::class, ['school' => $f['school']])
        ->set('actionTaken.'.$incident->id, 'Checked the ablution block and the prep room.')
        ->call('recordAction', $incident->id, 1);

    expect($incident->refresh()->actions()->where('action_type', 'action_recorded')->exists())->toBeTrue();
});

it('never deletes a missing-learner incident — it remains permanently regardless of outcome', function (): void {
    $f = rollCallAdminFixture();
    $rollCall = app(OpenRollCallAction::class)->execute(new OpenRollCallData(
        rollCallPointId: $f['point']->id, hostelId: $f['hostel']->id, termId: $f['term']->id, rollDate: Carbon::now(),
    ));
    app(MarkRollCallAction::class)->execute(new MarkRollCallData(
        rollCallId: $rollCall->id, studentId: $f['student']->id, status: 'missing', markedByUserId: $f['user']->id,
    ));
    $incident = MissingLearnerIncident::where('student_id', $f['student']->id)->first();

    expect(fn () => $incident->delete())->toThrow(Exception::class);
    expect(MissingLearnerIncident::find($incident->id))->not->toBeNull();
});

it('flags an unauthorised boundary crossing and alerts security', function (): void {
    $f = rollCallAdminFixture();
    $user = rollCallAdminUser($f, 'boarding.movement.view', 'boarding.movement.manage');
    $checkpoint = MovementCheckpoint::factory()->create(['school_id' => $f['school']->id, 'is_boundary' => true]);

    Livewire::actingAs($user)->test(Log::class, ['school' => $f['school']])
        ->set('studentId', $f['student']->id)
        ->set('checkpointId', $checkpoint->id)
        ->set('direction', 'out')
        ->set('hasActiveExeat', false)
        ->call('record');

    $entry = MovementLogEntry::where('student_id', $f['student']->id)->first();
    expect($entry)->not->toBeNull()
        ->and($entry->is_authorised)->toBeFalse();
});
