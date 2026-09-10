<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\Actions\AcknowledgeEscalationStepAction;
use Modules\Boarding\Domain\Actions\AdvanceEscalationLadderAction;
use Modules\Boarding\Domain\Actions\CloseIncidentAction;
use Modules\Boarding\Domain\Actions\CreateEscalationProfileAction;
use Modules\Boarding\Domain\Actions\CreateRollCallPointAction;
use Modules\Boarding\Domain\Actions\LocateLearnerAction;
use Modules\Boarding\Domain\Actions\MarkRollCallAction;
use Modules\Boarding\Domain\Actions\OpenRollCallAction;
use Modules\Boarding\Domain\Actions\RecordCheckpointMovementAction;
use Modules\Boarding\Domain\Actions\RecordEscalationActionAction;
use Modules\Boarding\Domain\DataObjects\AcknowledgeEscalationStepData;
use Modules\Boarding\Domain\DataObjects\CloseIncidentData;
use Modules\Boarding\Domain\DataObjects\CreateEscalationProfileData;
use Modules\Boarding\Domain\DataObjects\CreateRollCallPointData;
use Modules\Boarding\Domain\DataObjects\EscalationStepInput;
use Modules\Boarding\Domain\DataObjects\LocateLearnerData;
use Modules\Boarding\Domain\DataObjects\MarkRollCallData;
use Modules\Boarding\Domain\DataObjects\OpenRollCallData;
use Modules\Boarding\Domain\DataObjects\RecordCheckpointMovementData;
use Modules\Boarding\Domain\DataObjects\RecordEscalationActionData;
use Modules\Boarding\Domain\Exceptions\ActionRecordRequiredException;
use Modules\Boarding\Domain\Support\LiveOccupancyProvider;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelBed;
use Modules\Boarding\Models\HostelRoom;
use Modules\Boarding\Models\MissingLearnerIncident;
use Modules\Boarding\Models\MovementCheckpoint;
use Modules\Boarding\Models\RollCallRecord;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

// A manual `Carbon::setTestNow()` reset at the end of a test body never
// runs if an earlier expectation in that same test throws — leaking a
// frozen "now" into every test that runs afterward in the same process.
// `afterEach` runs unconditionally, pass or fail.
afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User}
 */
function brd02Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);
    $user = User::factory()->create();

    return compact('school', 'year', 'term', 'user');
}

/**
 * @param  array<string, mixed>  $f
 */
function brd02AllocateBoarder(array $f, string $firstName, ?Hostel $hostel = null, string $status = 'active'): array
{
    $hostel ??= Hostel::factory()->create(['school_id' => $f['school']->id, 'gender' => 'male', 'code' => 'H'.uniqid()]);
    $room = HostelRoom::factory()->create(['school_id' => $f['school']->id, 'hostel_id' => $hostel->id]);
    $bed = HostelBed::factory()->create(['school_id' => $f['school']->id, 'room_id' => $room->id]);
    $student = Student::factory()->boarder()->create(['school_id' => $f['school']->id, 'gender' => 'male', 'first_name' => $firstName, 'status' => $status]);

    $allocation = BedAllocation::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'student_id' => $student->id, 'bed_id' => $bed->id, 'hostel_id' => $hostel->id, 'room_id' => $room->id,
        'status' => 'confirmed', 'allocated_by' => $f['user']->id,
    ]);

    return ['hostel' => $hostel, 'student' => $student, 'allocation' => $allocation];
}

it('pre-populates a withdrawn learner and opens an incident immediately on a missing mark', function (): void {
    $f = brd02Fixture();
    $active = brd02AllocateBoarder($f, 'Active');
    $withdrawn = brd02AllocateBoarder($f, 'Withdrawn', hostel: $active['hostel'], status: 'withdrawn');

    $point = app(CreateRollCallPointAction::class)->execute(new CreateRollCallPointData(
        schoolId: $f['school']->id, code: 'LIGHTS_OUT', name: 'Lights Out',
        scheduledTime: '21:00:00', appliesOnDays: ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
    ));

    $rollCall = app(OpenRollCallAction::class)->execute(new OpenRollCallData(
        rollCallPointId: $point->id, hostelId: $active['hostel']->id, termId: $f['term']->id, rollDate: now(),
    ));

    expect($rollCall->expected_count)->toBe(2);

    $withdrawnRecord = RollCallRecord::where('roll_call_id', $rollCall->id)->where('student_id', $withdrawn['student']->id)->first();
    expect($withdrawnRecord->status)->toBe('withdrawn')
        ->and($withdrawnRecord->is_auto_populated)->toBeTrue();

    app(MarkRollCallAction::class)->execute(new MarkRollCallData(
        rollCallId: $rollCall->id, studentId: $active['student']->id, status: 'missing', markedByUserId: $f['user']->id,
    ));

    $incident = MissingLearnerIncident::where('student_id', $active['student']->id)->first();
    expect($incident)->not->toBeNull()
        ->and($incident->status)->toBe('open')
        ->and($incident->actions()->where('action_type', 'notified')->exists())->toBeTrue();
});

it('advances the escalation ladder on elapsed time regardless of acknowledgement', function (): void {
    $f = brd02Fixture();
    $boarder = brd02AllocateBoarder($f, 'Missing');

    $profile = app(CreateEscalationProfileAction::class)->execute(new CreateEscalationProfileData(
        schoolId: $f['school']->id, name: 'Standard Ladder',
        steps: [
            new EscalationStepInput(stepNumber: 1, delayMinutes: 0, channels: ['push'], messageTemplateKey: 'boarding.missing_learner_step', requiresActionRecord: true),
            new EscalationStepInput(stepNumber: 2, delayMinutes: 10, channels: ['push', 'sms'], messageTemplateKey: 'boarding.missing_learner_step'),
        ],
    ));

    $point = app(CreateRollCallPointAction::class)->execute(new CreateRollCallPointData(
        schoolId: $f['school']->id, code: 'LIGHTS_OUT', name: 'Lights Out',
        scheduledTime: '21:00:00', appliesOnDays: ['mon'], escalationProfileId: $profile->id,
    ));

    $rollCall = app(OpenRollCallAction::class)->execute(new OpenRollCallData(
        rollCallPointId: $point->id, hostelId: $boarder['hostel']->id, termId: $f['term']->id, rollDate: now(),
    ));

    app(MarkRollCallAction::class)->execute(new MarkRollCallData(
        rollCallId: $rollCall->id, studentId: $boarder['student']->id, status: 'missing', markedByUserId: $f['user']->id,
    ));

    $incident = MissingLearnerIncident::where('student_id', $boarder['student']->id)->firstOrFail();

    app(AcknowledgeEscalationStepAction::class)->execute(new AcknowledgeEscalationStepData(
        incidentId: $incident->id, stepNumber: 1, actorId: $f['user']->id,
    ));

    // Acknowledging step 1 must not stop the clock.
    Carbon::setTestNow(Carbon::now()->addMinutes(11));

    $advanced = app(AdvanceEscalationLadderAction::class)->execute($incident->id);

    expect($advanced->current_step)->toBe(2)
        ->and($advanced->status)->toBe('escalating');
});

it('rejects a bare acknowledgement for a step requiring an action record', function (): void {
    $f = brd02Fixture();
    $boarder = brd02AllocateBoarder($f, 'Missing');
    $point = app(CreateRollCallPointAction::class)->execute(new CreateRollCallPointData(
        schoolId: $f['school']->id, code: 'LIGHTS_OUT', name: 'Lights Out',
        scheduledTime: '21:00:00', appliesOnDays: ['mon'],
    ));
    $rollCall = app(OpenRollCallAction::class)->execute(new OpenRollCallData(
        rollCallPointId: $point->id, hostelId: $boarder['hostel']->id, termId: $f['term']->id, rollDate: now(),
    ));
    app(MarkRollCallAction::class)->execute(new MarkRollCallData(
        rollCallId: $rollCall->id, studentId: $boarder['student']->id, status: 'missing', markedByUserId: $f['user']->id,
    ));
    $incident = MissingLearnerIncident::where('student_id', $boarder['student']->id)->firstOrFail();

    expect(fn () => app(RecordEscalationActionAction::class)->execute(new RecordEscalationActionData(
        incidentId: $incident->id, stepNumber: 1, actorId: $f['user']->id, actionTaken: '   ',
    )))->toThrow(ActionRecordRequiredException::class);

    $recorded = app(RecordEscalationActionAction::class)->execute(new RecordEscalationActionData(
        incidentId: $incident->id, stepNumber: 1, actorId: $f['user']->id,
        actionTaken: 'Checked the ablution block and the prep room — not there.',
    ));

    expect($recorded->action_type)->toBe('action_recorded');
});

it('halts escalation when a learner is located and requires an outcome before closing', function (): void {
    $f = brd02Fixture();
    $boarder = brd02AllocateBoarder($f, 'Missing');
    $point = app(CreateRollCallPointAction::class)->execute(new CreateRollCallPointData(
        schoolId: $f['school']->id, code: 'LIGHTS_OUT', name: 'Lights Out',
        scheduledTime: '21:00:00', appliesOnDays: ['mon'],
    ));
    $rollCall = app(OpenRollCallAction::class)->execute(new OpenRollCallData(
        rollCallPointId: $point->id, hostelId: $boarder['hostel']->id, termId: $f['term']->id, rollDate: now(),
    ));
    app(MarkRollCallAction::class)->execute(new MarkRollCallData(
        rollCallId: $rollCall->id, studentId: $boarder['student']->id, status: 'missing', markedByUserId: $f['user']->id,
    ));
    $incident = MissingLearnerIncident::where('student_id', $boarder['student']->id)->firstOrFail();

    $located = app(LocateLearnerAction::class)->execute(new LocateLearnerData(
        incidentId: $incident->id, locatedByUserId: $f['user']->id, locationFound: 'Library',
        outcome: 'safe', outcomeNote: 'Was studying, phone on silent.',
    ));

    expect($located->status)->toBe('located');

    $closed = app(CloseIncidentAction::class)->execute(new CloseIncidentData(
        incidentId: $incident->id, closedByUserId: $f['user']->id,
    ));

    expect($closed->status)->toBe('resolved')->and($closed->closed_at)->not->toBeNull();
});

it('never allows a missing learner incident to be deleted, at any permission level', function (): void {
    $f = brd02Fixture();
    $boarder = brd02AllocateBoarder($f, 'Missing');
    $point = app(CreateRollCallPointAction::class)->execute(new CreateRollCallPointData(
        schoolId: $f['school']->id, code: 'LIGHTS_OUT', name: 'Lights Out',
        scheduledTime: '21:00:00', appliesOnDays: ['mon'],
    ));
    $rollCall = app(OpenRollCallAction::class)->execute(new OpenRollCallData(
        rollCallPointId: $point->id, hostelId: $boarder['hostel']->id, termId: $f['term']->id, rollDate: now(),
    ));
    app(MarkRollCallAction::class)->execute(new MarkRollCallData(
        rollCallId: $rollCall->id, studentId: $boarder['student']->id, status: 'missing', markedByUserId: $f['user']->id,
    ));
    $incident = MissingLearnerIncident::where('student_id', $boarder['student']->id)->firstOrFail();

    expect(fn () => $incident->delete())->toThrow(InvalidStateTransitionException::class)
        ->and(fn () => $incident->update(['student_id' => $boarder['student']->id + 999]))->toThrow(InvalidStateTransitionException::class)
        ->and(MissingLearnerIncident::find($incident->id))->not->toBeNull();
});

it('flags an unauthorised boundary crossing when there is no active exeat', function (): void {
    $f = brd02Fixture();
    $boarder = brd02AllocateBoarder($f, 'Gatecrasher');
    $checkpoint = MovementCheckpoint::factory()->create(['school_id' => $f['school']->id, 'is_boundary' => true]);

    $entry = app(RecordCheckpointMovementAction::class)->execute(new RecordCheckpointMovementData(
        studentId: $boarder['student']->id, checkpointId: $checkpoint->id, direction: 'out', method: 'manual',
    ));

    expect($entry->is_authorised)->toBeFalse();

    $authorised = app(RecordCheckpointMovementAction::class)->execute(new RecordCheckpointMovementData(
        studentId: $boarder['student']->id, checkpointId: $checkpoint->id, direction: 'out', method: 'manual',
        hasActiveExeat: true,
    ));

    expect($authorised->is_authorised)->toBeTrue();
});

it('reports live present occupancy from the roll call, not the nominal allocated count', function (): void {
    $f = brd02Fixture();
    $hostel = Hostel::factory()->create(['school_id' => $f['school']->id, 'gender' => 'male', 'code' => 'H'.uniqid()]);
    $room = HostelRoom::factory()->create(['school_id' => $f['school']->id, 'hostel_id' => $hostel->id]);
    $point = app(CreateRollCallPointAction::class)->execute(new CreateRollCallPointData(
        schoolId: $f['school']->id, code: 'SUPPER', name: 'Supper', scheduledTime: '18:00:00', appliesOnDays: ['mon'],
    ));

    $present = [];
    $onExeat = [];

    foreach (range(1, 5) as $i) {
        $bed = HostelBed::factory()->create(['school_id' => $f['school']->id, 'room_id' => $room->id]);
        $student = Student::factory()->boarder()->create(['school_id' => $f['school']->id, 'gender' => 'male']);
        BedAllocation::factory()->create([
            'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
            'student_id' => $student->id, 'bed_id' => $bed->id, 'hostel_id' => $hostel->id, 'room_id' => $room->id,
            'status' => 'confirmed', 'allocated_by' => $f['user']->id,
        ]);

        if ($i <= 3) {
            $present[] = $student;
        } else {
            $onExeat[] = $student;
        }
    }

    $rollCall = app(OpenRollCallAction::class)->execute(new OpenRollCallData(
        rollCallPointId: $point->id, hostelId: $hostel->id, termId: $f['term']->id, rollDate: now(),
    ));

    foreach ($present as $student) {
        app(MarkRollCallAction::class)->execute(new MarkRollCallData(
            rollCallId: $rollCall->id, studentId: $student->id, status: 'present', markedByUserId: $f['user']->id,
        ));
    }

    foreach ($onExeat as $student) {
        app(MarkRollCallAction::class)->execute(new MarkRollCallData(
            rollCallId: $rollCall->id, studentId: $student->id, status: 'exeat', markedByUserId: $f['user']->id, note: 'Weekend exeat.',
        ));
    }

    $rollCall->update(['status' => 'completed', 'completed_at' => now()]);

    $occupancy = app(LiveOccupancyProvider::class)->liveOccupancy(now(), 'supper', $hostel->id);

    expect($occupancy->allocated)->toBe(5)
        ->and($occupancy->present)->toBe(3)
        ->and($occupancy->onExeat)->toBe(2)
        ->and($occupancy->rollCallAvailable)->toBeTrue();
});
