<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Modules\Boarding\Domain\Events\InspectionRecorded;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelRoom;
use Modules\Boarding\Models\RollCall;
use Modules\Boarding\Models\RollCallPoint;
use Modules\Boarding\Models\RoomInspection;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\House;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Facilities\Models\BookableResource;
use Modules\Finance\Models\AdHocCharge;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Sport\Domain\Actions\CheckOverdueEquipmentAction;
use Modules\Sport\Domain\Actions\ComputeHouseLeaderboardAction;
use Modules\Sport\Domain\Actions\ConfirmFixtureAction;
use Modules\Sport\Domain\Actions\CreateActivityAction;
use Modules\Sport\Domain\Actions\CreateAwardAction;
use Modules\Sport\Domain\Actions\CreateHouseCompetitionAction;
use Modules\Sport\Domain\Actions\CreateTeamAction;
use Modules\Sport\Domain\Actions\IssueEquipmentAction;
use Modules\Sport\Domain\Actions\JoinActivityAction;
use Modules\Sport\Domain\Actions\MarkSquadRollStatusForFixtureAction;
use Modules\Sport\Domain\Actions\RecordFixtureInjuryAction;
use Modules\Sport\Domain\Actions\RecordHouseCompetitionResultAction;
use Modules\Sport\Domain\Actions\RecordManualHousePointsAction;
use Modules\Sport\Domain\Actions\ReturnEquipmentAction;
use Modules\Sport\Domain\Actions\ScheduleFixtureAction;
use Modules\Sport\Domain\Actions\SelectFixtureSquadAction;
use Modules\Sport\Domain\DataObjects\ConfirmFixtureData;
use Modules\Sport\Domain\DataObjects\CreateActivityData;
use Modules\Sport\Domain\DataObjects\CreateAwardData;
use Modules\Sport\Domain\DataObjects\CreateHouseCompetitionData;
use Modules\Sport\Domain\DataObjects\CreateTeamData;
use Modules\Sport\Domain\DataObjects\IssueEquipmentData;
use Modules\Sport\Domain\DataObjects\JoinActivityData;
use Modules\Sport\Domain\DataObjects\MarkSquadRollStatusForFixtureData;
use Modules\Sport\Domain\DataObjects\RecordFixtureInjuryData;
use Modules\Sport\Domain\DataObjects\RecordHouseCompetitionResultData;
use Modules\Sport\Domain\DataObjects\RecordManualHousePointsData;
use Modules\Sport\Domain\DataObjects\ScheduleFixtureData;
use Modules\Sport\Domain\DataObjects\SelectFixtureSquadData;
use Modules\Sport\Domain\Events\EquipmentOverdue;
use Modules\Sport\Domain\Exceptions\ActivityCapacityExceededException;
use Modules\Sport\Domain\Exceptions\GuardianConsentRequiredException;
use Modules\Sport\Domain\Exceptions\MedicalClearanceRequiredException;
use Modules\Stores\Models\FixedAsset;
use Modules\Transport\Models\Driver;
use Modules\Transport\Models\TripPassenger;
use Modules\Transport\Models\Vehicle;
use Modules\Welfare\Domain\Actions\RecordBehaviourAction;
use Modules\Welfare\Domain\DataObjects\RecordBehaviourData;
use Modules\Welfare\Models\BehaviourCategory;
use Modules\Welfare\Models\HealthIncident;
use Modules\Welfare\Models\MedicalCondition;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User}
 */
function ops07Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create([
        'is_current' => true, 'financial_state' => 'open',
        'starts_on' => now()->subMonth()->toDateString(), 'ends_on' => now()->addMonths(2)->toDateString(),
    ]);
    $user = User::factory()->create();

    foreach (['resource_booking'] as $type) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $type, pattern: strtoupper(substr($type, 0, 3)).'/{SEQ:6}',
        ));
    }

    return compact('school', 'year', 'term', 'user');
}

it('refuses to join an activity that requires guardian consent without it, and requires medical clearance where flagged (BR-OPS-07-001/002/AC-OPS-07-001)', function (): void {
    $f = ops07Fixture();
    $activity = app(CreateActivityAction::class)->execute(new CreateActivityData(
        schoolId: $f['school']->id, code: 'RUG', name: 'Rugby', activityType: 'sport',
        requiresMedicalClearance: true, requiresGuardianConsent: true,
    ));
    $student = Student::factory()->for($f['school'])->create();

    expect(fn () => app(JoinActivityAction::class)->execute(new JoinActivityData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        activityId: $activity->id, studentId: $student->id, raisedByUserId: $f['user']->id,
        consentReceived: false,
    )))->toThrow(GuardianConsentRequiredException::class);

    MedicalCondition::factory()->create([
        'school_id' => $f['school']->id, 'student_id' => $student->id,
        'affects_physical_activity' => true, 'status' => 'active',
    ]);

    expect(fn () => app(JoinActivityAction::class)->execute(new JoinActivityData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        activityId: $activity->id, studentId: $student->id, raisedByUserId: $f['user']->id,
        consentReceived: true, medicalCleared: null,
    )))->toThrow(MedicalClearanceRequiredException::class);

    $membership = app(JoinActivityAction::class)->execute(new JoinActivityData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        activityId: $activity->id, studentId: $student->id, raisedByUserId: $f['user']->id,
        consentReceived: true, medicalCleared: true,
    ));

    expect($membership->status)->toBe('active');
});

it('bills a pro-rated fee for a mid-term joiner through the real FIN-02 ad hoc charge engine (BR-OPS-07-003/AC-OPS-07-003)', function (): void {
    $f = ops07Fixture();
    $activity = app(CreateActivityAction::class)->execute(new CreateActivityData(
        schoolId: $f['school']->id, code: 'CHS', name: 'Chess Club', activityType: 'club',
        requiresGuardianConsent: false,
    ));
    $feeComponent = FeeComponent::factory()->create(['school_id' => $f['school']->id]);
    $activity->update(['fee_component_id' => $feeComponent->id]);
    $student = Student::factory()->for($f['school'])->create();

    $membership = app(JoinActivityAction::class)->execute(new JoinActivityData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        activityId: $activity->id, studentId: $student->id, raisedByUserId: $f['user']->id,
        joinedOn: $f['term']->starts_on->copy()->addMonth(),
        fullTermFeeMinor: 6000, feeCurrency: 'USD',
    ));

    expect($membership->billing_status)->toBe('charged')
        ->and($membership->ad_hoc_charge_id)->not->toBeNull();

    $charge = AdHocCharge::findOrFail($membership->ad_hoc_charge_id);
    expect($charge->amount_minor)->toBeLessThan(6000)
        ->and($charge->amount_minor)->toBeGreaterThan(0);
});

it('refuses membership beyond max_participants without an override (BR-OPS-07-004)', function (): void {
    $f = ops07Fixture();
    $activity = app(CreateActivityAction::class)->execute(new CreateActivityData(
        schoolId: $f['school']->id, code: 'NET', name: 'Netball', activityType: 'sport',
        requiresGuardianConsent: false, maxParticipants: 1,
    ));
    $first = Student::factory()->for($f['school'])->create();
    $second = Student::factory()->for($f['school'])->create();

    app(JoinActivityAction::class)->execute(new JoinActivityData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        activityId: $activity->id, studentId: $first->id, raisedByUserId: $f['user']->id,
    ));

    expect(fn () => app(JoinActivityAction::class)->execute(new JoinActivityData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        activityId: $activity->id, studentId: $second->id, raisedByUserId: $f['user']->id,
    )))->toThrow(ActivityCapacityExceededException::class);

    $membership = app(JoinActivityAction::class)->execute(new JoinActivityData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        activityId: $activity->id, studentId: $second->id, raisedByUserId: $f['user']->id,
        overrideCapacity: true, overrideReason: 'Head of sport approved an extra place.',
    ));

    expect($membership->status)->toBe('active');
});

it('refuses to select an uncleared learner for a squad, notifies guardians on selection, and marks the squad fixture for roll call (BR-OPS-07-002/005/007/AC-OPS-07-001)', function (): void {
    $f = ops07Fixture();
    $activity = app(CreateActivityAction::class)->execute(new CreateActivityData(
        schoolId: $f['school']->id, code: 'RUG', name: 'Rugby', activityType: 'sport',
        requiresMedicalClearance: true, requiresGuardianConsent: false,
    ));
    $team = app(CreateTeamAction::class)->execute(new CreateTeamData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, activityId: $activity->id, name: '1st XV',
    ));
    $fixture = app(ScheduleFixtureAction::class)->execute(new ScheduleFixtureData(
        schoolId: $f['school']->id, termId: $f['term']->id, teamId: $team->id, opponent: 'St Georges',
        fixtureType: 'friendly', venueType: 'home', fixtureDate: now()->addWeek(),
    ));

    $uncleared = Student::factory()->for($f['school'])->create();
    MedicalCondition::factory()->create([
        'school_id' => $f['school']->id, 'student_id' => $uncleared->id,
        'affects_physical_activity' => true, 'status' => 'active',
    ]);

    expect(fn () => app(SelectFixtureSquadAction::class)->execute(new SelectFixtureSquadData(
        fixtureId: $fixture->id, squadStudentIds: [$uncleared->id],
    )))->toThrow(MedicalClearanceRequiredException::class);

    $player = Student::factory()->for($f['school'])->create();
    $guardian = Guardian::factory()->for($f['school'])->create();
    StudentGuardian::factory()->create([
        'school_id' => $f['school']->id, 'student_id' => $player->id, 'guardian_id' => $guardian->id,
    ]);

    $fixture = app(SelectFixtureSquadAction::class)->execute(new SelectFixtureSquadData(
        fixtureId: $fixture->id, squadStudentIds: [$player->id],
    ));

    expect($fixture->squad_student_ids)->toBe([$player->id])
        ->and($fixture->guardians_notified_at)->not->toBeNull();

    $point = RollCallPoint::factory()->create(['school_id' => $f['school']->id]);
    $rollCall = RollCall::factory()->create([
        'school_id' => $f['school']->id, 'term_id' => $f['term']->id, 'roll_call_point_id' => $point->id,
    ]);

    $records = app(MarkSquadRollStatusForFixtureAction::class)->execute(new MarkSquadRollStatusForFixtureData(
        fixtureId: $fixture->id, rollCallIds: [$rollCall->id], markedByUserId: $f['user']->id,
    ));

    expect($records->first()->status)->toBe('fixture');
});

it('creates a real OPS-01 trip with the squad as passengers for an away fixture (BR-OPS-07-006/AC-OPS-07-002)', function (): void {
    $f = ops07Fixture();
    $activity = app(CreateActivityAction::class)->execute(new CreateActivityData(
        schoolId: $f['school']->id, code: 'RUG', name: 'Rugby', activityType: 'sport', requiresGuardianConsent: false,
    ));
    $team = app(CreateTeamAction::class)->execute(new CreateTeamData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, activityId: $activity->id, name: '1st XV',
    ));
    $fixture = app(ScheduleFixtureAction::class)->execute(new ScheduleFixtureData(
        schoolId: $f['school']->id, termId: $f['term']->id, teamId: $team->id, opponent: 'St Georges',
        fixtureType: 'friendly', venueType: 'away', venueName: 'St Georges College', fixtureDate: now()->addWeek(),
    ));
    $player = Student::factory()->for($f['school'])->create();
    $fixture = app(SelectFixtureSquadAction::class)->execute(new SelectFixtureSquadData(
        fixtureId: $fixture->id, squadStudentIds: [$player->id],
    ));

    $vehicle = Vehicle::factory()->create(['school_id' => $f['school']->id]);
    $driver = Driver::factory()->create(['school_id' => $f['school']->id]);

    $fixture = app(ConfirmFixtureAction::class)->execute(new ConfirmFixtureData(
        fixtureId: $fixture->id, confirmedByUserId: $f['user']->id,
        vehicleId: $vehicle->id, driverId: $driver->id,
    ));

    expect($fixture->status)->toBe('confirmed')
        ->and($fixture->trip_id)->not->toBeNull();

    $passenger = TripPassenger::where('trip_id', $fixture->trip_id)->first();
    expect($passenger)->not->toBeNull()
        ->and($passenger->student_id)->toBe($player->id);
});

it('creates a real OPS-05 internal booking for a home fixture (BR-OPS-07-006)', function (): void {
    $f = ops07Fixture();
    $activity = app(CreateActivityAction::class)->execute(new CreateActivityData(
        schoolId: $f['school']->id, code: 'NET', name: 'Netball', activityType: 'sport', requiresGuardianConsent: false,
    ));
    $team = app(CreateTeamAction::class)->execute(new CreateTeamData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, activityId: $activity->id, name: '1st VII',
    ));
    $fixture = app(ScheduleFixtureAction::class)->execute(new ScheduleFixtureData(
        schoolId: $f['school']->id, termId: $f['term']->id, teamId: $team->id, opponent: 'Visiting School',
        fixtureType: 'friendly', venueType: 'home', fixtureDate: now()->addWeek(), startTime: '14:00:00',
    ));
    $resource = BookableResource::factory()->create(['school_id' => $f['school']->id]);

    $fixture = app(ConfirmFixtureAction::class)->execute(new ConfirmFixtureData(
        fixtureId: $fixture->id, confirmedByUserId: $f['user']->id, resourceId: $resource->id,
    ));

    expect($fixture->status)->toBe('confirmed')
        ->and($fixture->booking_id)->not->toBeNull();
});

it('raises a real BRD-06 health incident linked to the fixture and always notifies the guardian (BR-OPS-07-012/AC-OPS-07-005)', function (): void {
    $f = ops07Fixture();
    $activity = app(CreateActivityAction::class)->execute(new CreateActivityData(
        schoolId: $f['school']->id, code: 'RUG', name: 'Rugby', activityType: 'sport', requiresGuardianConsent: false,
    ));
    $team = app(CreateTeamAction::class)->execute(new CreateTeamData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, activityId: $activity->id, name: '1st XV',
    ));
    $fixture = app(ScheduleFixtureAction::class)->execute(new ScheduleFixtureData(
        schoolId: $f['school']->id, termId: $f['term']->id, teamId: $team->id, opponent: 'St Georges',
        fixtureType: 'friendly', venueType: 'away', venueName: 'St Georges College', fixtureDate: now(),
    ));
    $player = Student::factory()->for($f['school'])->create();
    $guardian = Guardian::factory()->for($f['school'])->create();
    StudentGuardian::factory()->create([
        'school_id' => $f['school']->id, 'student_id' => $player->id, 'guardian_id' => $guardian->id,
        'is_primary_contact' => true,
    ]);

    $incident = app(RecordFixtureInjuryAction::class)->execute(new RecordFixtureInjuryData(
        fixtureId: $fixture->id, termId: $f['term']->id, studentId: $player->id,
        incidentType: 'sprain', occurredAt: now(), description: 'Twisted ankle during the match.',
        severity: 'minor', reportedByUserId: $f['user']->id,
    ));

    expect($incident->fixture_id)->toBe($fixture->id)
        ->and($incident->guardian_notified_at)->not->toBeNull();

    $stored = HealthIncident::findOrFail($incident->id);
    expect($stored->fixture_id)->toBe($fixture->id);
});

it('aggregates house points from a competition, real behaviour records and real room inspections at their configured weights (BR-OPS-07-008/009/AC-OPS-07-004)', function (): void {
    $f = ops07Fixture();
    $house = House::factory()->create(['school_id' => $f['school']->id]);

    $competition = app(CreateHouseCompetitionAction::class)->execute(new CreateHouseCompetitionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Athletics', competitionType: 'sport',
        pointsScheme: [1 => 10, 2 => 7], weight: '2',
    ));
    app(RecordHouseCompetitionResultAction::class)->execute(new RecordHouseCompetitionResultData(
        competitionId: $competition->id, placements: [['house_id' => $house->id, 'place' => 1]],
        termId: $f['term']->id, awardedByUserId: $f['user']->id,
    ));

    $behaviourStudent = Student::factory()->for($f['school'])->create(['house_id' => $house->id]);
    $category = BehaviourCategory::factory()->positive()->create(['school_id' => $f['school']->id]);
    app(RecordBehaviourAction::class)->execute(new RecordBehaviourData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        studentId: $behaviourStudent->id, categoryId: $category->id, occurredAt: now(),
        description: 'Excellent conduct in class.', reportedByUserId: $f['user']->id,
    ));

    $hostel = Hostel::factory()->create(['school_id' => $f['school']->id, 'code' => 'H-'.uniqid(), 'house_id' => $house->id]);
    $room = HostelRoom::factory()->create(['school_id' => $f['school']->id, 'hostel_id' => $hostel->id]);
    $staff = Staff::factory()->for($f['school'])->create();
    $inspection = RoomInspection::factory()->create([
        'school_id' => $f['school']->id, 'term_id' => $f['term']->id, 'room_id' => $room->id,
        'inspector_staff_id' => $staff->id, 'total_score' => 8, 'max_score' => 10,
    ]);
    event(new InspectionRecorded($inspection));

    $leaderboard = app(ComputeHouseLeaderboardAction::class)->execute($f['school']->id, $f['year']->id);
    $entry = $leaderboard->firstWhere('houseId', $house->id);

    expect($entry)->not->toBeNull()
        ->and($entry->bySource['competition'] ?? null)->toBe(20.0)
        ->and($entry->bySource)->toHaveKey('behaviour')
        ->and($entry->bySource)->toHaveKey('inspection')
        ->and($entry->totalPoints)->toBeGreaterThan(20.0);
});

it('records manual house points and creates an award (BR-OPS-07-008/010)', function (): void {
    $f = ops07Fixture();
    $house = House::factory()->create(['school_id' => $f['school']->id]);
    $student = Student::factory()->for($f['school'])->create();

    $point = app(RecordManualHousePointsAction::class)->execute(new RecordManualHousePointsData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        houseId: $house->id, points: 5, awardedByUserId: $f['user']->id, reason: 'Community service.',
    ));

    expect($point->source_type)->toBe('manual');

    $award = app(CreateAwardAction::class)->execute(new CreateAwardData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, studentId: $student->id,
        awardType: 'half_colours', title: 'Half Colours — Rugby', awardedOn: now(),
        awardedByUserId: $f['user']->id,
    ));

    expect($award->appears_on_report_card)->toBeTrue()
        ->and($award->appears_on_transcript)->toBeTrue();
});

it('tracks equipment issue, return and overdue (BR-OPS-07-011)', function (): void {
    Event::fake([EquipmentOverdue::class]);
    $f = ops07Fixture();
    $activity = app(CreateActivityAction::class)->execute(new CreateActivityData(
        schoolId: $f['school']->id, code: 'RUG', name: 'Rugby', activityType: 'sport', requiresGuardianConsent: false,
    ));
    $student = Student::factory()->for($f['school'])->create();
    $asset = FixedAsset::factory()->create(['school_id' => $f['school']->id]);

    $issue = app(IssueEquipmentAction::class)->execute(new IssueEquipmentData(
        schoolId: $f['school']->id, activityId: $activity->id, assetId: $asset->id, studentId: $student->id,
        issuedByUserId: $f['user']->id, expectedReturnOn: Carbon::yesterday(),
    ));

    expect($issue->returned_at)->toBeNull();

    $overdue = app(CheckOverdueEquipmentAction::class)->execute($f['school']->id);
    expect($overdue->pluck('id'))->toContain($issue->id);
    Event::assertDispatched(EquipmentOverdue::class);

    $returned = app(ReturnEquipmentAction::class)->execute($issue->id, 'good');
    expect($returned->returned_at)->not->toBeNull();

    $overdueAfterReturn = app(CheckOverdueEquipmentAction::class)->execute($f['school']->id);
    expect($overdueAfterReturn->pluck('id'))->not->toContain($issue->id);
});
