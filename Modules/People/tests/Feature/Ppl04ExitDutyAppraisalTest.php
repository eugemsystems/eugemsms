<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Auth\UserStatus;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\DataAccessLogEntry;
use Modules\Core\Models\File;
use Modules\Core\Models\PersonalAccessToken;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Domain\Actions\AddStaffDocumentAction;
use Modules\People\Domain\Actions\AdvanceDisciplinaryCaseStageAction;
use Modules\People\Domain\Actions\CheckStaffDocumentExpiryAction;
use Modules\People\Domain\Actions\ClearExitChecklistItemAction;
use Modules\People\Domain\Actions\CreateDutyRosterAction;
use Modules\People\Domain\Actions\CreateStaffAction;
use Modules\People\Domain\Actions\CreateStaffAppraisalAction;
use Modules\People\Domain\Actions\GenerateDutyRosterAction;
use Modules\People\Domain\Actions\InitiateStaffExitAction;
use Modules\People\Domain\Actions\ProcessStaffExitAction;
use Modules\People\Domain\Actions\RecordAppraisalMeetingAction;
use Modules\People\Domain\Actions\ReleaseFinalPayAction;
use Modules\People\Domain\Actions\ReportDisciplinaryCaseAction;
use Modules\People\Domain\Actions\SignOffAppraisalAction;
use Modules\People\Domain\Actions\SubmitAppraiserAssessmentAction;
use Modules\People\Domain\Actions\SubmitSelfAssessmentAction;
use Modules\People\Domain\Actions\SwapDutyAssignmentAction;
use Modules\People\Domain\Actions\ViewDisciplinaryCaseAction;
use Modules\People\Domain\DataObjects\AddStaffDocumentData;
use Modules\People\Domain\DataObjects\AdvanceDisciplinaryCaseStageData;
use Modules\People\Domain\DataObjects\ClearExitChecklistItemData;
use Modules\People\Domain\DataObjects\CreateDutyRosterData;
use Modules\People\Domain\DataObjects\CreateStaffAppraisalData;
use Modules\People\Domain\DataObjects\CreateStaffData;
use Modules\People\Domain\DataObjects\DutySlot;
use Modules\People\Domain\DataObjects\GenerateDutyRosterData;
use Modules\People\Domain\DataObjects\InitiateStaffExitData;
use Modules\People\Domain\DataObjects\ProcessStaffExitData;
use Modules\People\Domain\DataObjects\ReleaseFinalPayData;
use Modules\People\Domain\DataObjects\ReportDisciplinaryCaseData;
use Modules\People\Domain\DataObjects\SignOffAppraisalData;
use Modules\People\Domain\DataObjects\SubmitAppraiserAssessmentData;
use Modules\People\Domain\DataObjects\SubmitSelfAssessmentData;
use Modules\People\Domain\DataObjects\SwapDutyAssignmentData;
use Modules\People\Domain\DataObjects\ViewDisciplinaryCaseData;
use Modules\People\Domain\Events\StaffDocumentExpiring;
use Modules\People\Domain\Exceptions\ExitClearanceIncompleteException;
use Modules\People\Models\DutyAssignment;
use Modules\People\Models\LeaveRequest;
use Modules\People\Models\LeaveType;
use Modules\People\Models\Staff;

/**
 * Duplicated from `Ppl04StaffTest.php` rather than shared, since Pest
 * loads test files independently when run by path — a global
 * function defined in one file isn't guaranteed available when
 * another runs in isolation.
 *
 * @return array<string, mixed>
 */
function ppl04bFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    $user = User::factory()->create();

    foreach (['staff' => 'STF', 'disciplinary_case' => 'DISC'] as $documentType => $prefix) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $documentType, pattern: $prefix.'/{SEQ:6}',
        ));
    }

    $subject = Subject::factory()->for($school)->create();
    $class = SchoolClass::factory()->for($school)->for($year)->create();

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'user' => $user, 'subject' => $subject, 'class' => $class,
    ];
}

/**
 * @param  array<string, mixed>  $f
 * @param  array<string, mixed>  $overrides
 */
function createTeachingStaffB(array $f, array $overrides = []): Staff
{
    return app(CreateStaffAction::class)->execute(new CreateStaffData(
        schoolId: $f['school']->id,
        firstName: $overrides['firstName'] ?? 'Rudo',
        lastName: $overrides['lastName'] ?? 'Chikwava',
        dateOfBirth: now()->subYears(35),
        gender: 'female',
        primaryPhone: '+263771112222',
        staffCategory: 'teaching',
        joinedOn: now()->subYears(2),
        createdByUserId: $f['user']->id,
        isTeaching: $overrides['isTeaching'] ?? true,
        maxWeeklyPeriods: $overrides['maxWeeklyPeriods'] ?? 30,
    ));
}

it('flags a compliance-critical document within the alert window (AC-PPL-04-006)', function (): void {
    Event::fake([StaffDocumentExpiring::class]);

    $f = ppl04bFixture();
    $staff = createTeachingStaffB($f);
    $file = File::factory()->create(['school_id' => $f['school']->id]);

    $document = app(AddStaffDocumentAction::class)->execute(new AddStaffDocumentData(
        schoolId: $f['school']->id, staffId: $staff->id, documentType: 'police_clearance',
        fileId: $file->id, expiresOn: now()->addDays(25),
    ));

    $results = app(CheckStaffDocumentExpiryAction::class)->execute($f['school']->id);

    expect($results)->toHaveCount(1)
        ->and($results[0]['daysRemaining'])->toBe(25)
        ->and($results[0]['isComplianceCritical'])->toBeTrue()
        ->and($results[0]['document']->id)->toBe($document->id);

    Event::assertDispatched(StaffDocumentExpiring::class);
});

it('distributes duties within one of the mean and skips staff on approved leave (AC-PPL-04-010)', function (): void {
    $f = ppl04bFixture();
    $staffA = createTeachingStaffB($f, ['firstName' => 'A']);
    $staffB = createTeachingStaffB($f, ['firstName' => 'B']);
    $staffC = createTeachingStaffB($f, ['firstName' => 'C']);

    $leaveType = LeaveType::factory()->for($f['school'])->create(['is_paid' => true, 'code' => 'ANN2']);
    LeaveRequest::factory()->create([
        'school_id' => $f['school']->id, 'staff_id' => $staffC->id, 'leave_type_id' => $leaveType->id,
        'academic_year_id' => $f['year']->id, 'status' => 'approved',
        'starts_on' => now()->toDateString(), 'ends_on' => now()->addWeeks(6)->toDateString(), 'working_days' => 42,
    ]);

    $roster = app(CreateDutyRosterAction::class)->execute(new CreateDutyRosterData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        dutyType: 'teacher_on_duty', name: 'Teacher on Duty', rotationPattern: 'weekly',
        eligibleCategories: ['teaching'],
    ));

    $slots = collect(range(0, 5))->map(fn (int $i): DutySlot => new DutySlot(
        now()->addWeeks($i), now()->addWeeks($i)->addDays(6),
    ))->all();

    $result = app(GenerateDutyRosterAction::class)->execute(new GenerateDutyRosterData(rosterId: $roster->id, slots: $slots));

    expect($result['assignments'])->toHaveCount(6);

    $counts = DutyAssignment::where('roster_id', $roster->id)->get()->groupBy('staff_id')->map->count();

    expect($counts->get($staffC->id, 0))->toBe(0)
        ->and($counts->max() - $counts->min())->toBeLessThanOrEqual(1);
});

it('swaps a duty assignment, keeping both the original and replacement visible (BR-PPL-04-016)', function (): void {
    $f = ppl04bFixture();
    $original = createTeachingStaffB($f, ['firstName' => 'Orig']);
    $replacement = createTeachingStaffB($f, ['firstName' => 'Repl']);

    $roster = app(CreateDutyRosterAction::class)->execute(new CreateDutyRosterData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        dutyType: 'weekend_duty', name: 'Weekend Duty', rotationPattern: 'weekly',
    ));

    $assignment = DutyAssignment::factory()->create([
        'school_id' => $f['school']->id, 'roster_id' => $roster->id, 'staff_id' => $original->id, 'status' => 'assigned',
    ]);

    $swapped = app(SwapDutyAssignmentAction::class)->execute(new SwapDutyAssignmentData(
        assignmentId: $assignment->id, newStaffId: $replacement->id, approvedByUserId: $f['user']->id, bothPartiesConsented: true,
    ));

    expect($assignment->fresh()->status)->toBe('swapped')
        ->and($assignment->fresh()->swapped_with_staff_id)->toBe($replacement->id)
        ->and($swapped->staff_id)->toBe($replacement->id)
        ->and($swapped->status)->toBe('assigned')
        ->and(DutyAssignment::where('roster_id', $roster->id)->count())->toBe(2);
});

it('runs an appraisal through every stage to sign-off', function (): void {
    $f = ppl04bFixture();
    $staff = createTeachingStaffB($f);
    $appraiser = createTeachingStaffB($f, ['firstName' => 'Boss']);

    $appraisal = app(CreateStaffAppraisalAction::class)->execute(new CreateStaffAppraisalData(
        schoolId: $f['school']->id, staffId: $staff->id, academicYearId: $f['year']->id,
        cycle: 'annual', appraiserStaffId: $appraiser->id,
    ));

    app(SubmitSelfAssessmentAction::class)->execute(new SubmitSelfAssessmentData(
        appraisalId: $appraisal->id, selfAssessment: ['summary' => 'Good year'],
    ));

    app(SubmitAppraiserAssessmentAction::class)->execute(new SubmitAppraiserAssessmentData(
        appraisalId: $appraisal->id, appraiserAssessment: ['summary' => 'Agreed'], overallRating: 'exceeds',
    ));

    app(RecordAppraisalMeetingAction::class)->execute($appraisal->id);

    $signedOff = app(SignOffAppraisalAction::class)->execute(new SignOffAppraisalData(
        appraisalId: $appraisal->id, staffComments: 'Agreed with the assessment',
    ));

    expect($signedOff->status)->toBe('signed_off')
        ->and($signedOff->signed_off_at)->not->toBeNull()
        ->and($signedOff->overall_rating)->toBe('exceeds');
});

it('logs every view of a disciplinary case and enforces valid stage transitions (BR-PPL-04-020)', function (): void {
    $f = ppl04bFixture();
    $staff = createTeachingStaffB($f);
    $viewer = User::factory()->create();

    $case = app(ReportDisciplinaryCaseAction::class)->execute(new ReportDisciplinaryCaseData(
        schoolId: $f['school']->id, staffId: $staff->id, category: 'conduct',
        description: 'Late for duty repeatedly', incidentDate: now()->subDay(), reportedByUserId: $f['user']->id,
    ));

    expect($case->is_confidential)->toBeTrue()
        ->and($case->stage)->toBe('reported');

    app(ViewDisciplinaryCaseAction::class)->execute(new ViewDisciplinaryCaseData(caseId: $case->id, viewedByUserId: $viewer->id));

    expect(DataAccessLogEntry::where('resource_type', 'staff_disciplinary_case')->where('resource_id', $case->id)->where('user_id', $viewer->id)->exists())->toBeTrue();

    expect(fn () => app(AdvanceDisciplinaryCaseStageAction::class)->execute(new AdvanceDisciplinaryCaseStageData(
        caseId: $case->id, targetStage: 'decided',
    )))->toThrow(InvalidStateTransitionException::class);

    app(AdvanceDisciplinaryCaseStageAction::class)->execute(new AdvanceDisciplinaryCaseStageData(caseId: $case->id, targetStage: 'investigation'));
    app(AdvanceDisciplinaryCaseStageAction::class)->execute(new AdvanceDisciplinaryCaseStageData(caseId: $case->id, targetStage: 'hearing'));

    expect(fn () => app(AdvanceDisciplinaryCaseStageAction::class)->execute(new AdvanceDisciplinaryCaseStageData(
        caseId: $case->id, targetStage: 'decided',
    )))->toThrow(InvalidArgumentException::class);

    $decided = app(AdvanceDisciplinaryCaseStageAction::class)->execute(new AdvanceDisciplinaryCaseStageData(
        caseId: $case->id, targetStage: 'decided', outcome: 'written_warning', outcomeDate: now(),
    ));

    expect($decided->stage)->toBe('decided')
        ->and($decided->outcome)->toBe('written_warning');
});

it('blocks final pay release until every clearance item is cleared, naming what is outstanding (AC-PPL-04-008)', function (): void {
    $f = ppl04bFixture();
    $staff = createTeachingStaffB($f);

    $checklist = app(InitiateStaffExitAction::class)->execute(new InitiateStaffExitData(
        staffId: $staff->id, initiatedByUserId: $f['user']->id,
    ));

    expect($checklist->items)->toHaveCount(5)
        ->and($checklist->isFullyCleared())->toBeFalse();

    expect(fn () => app(ReleaseFinalPayAction::class)->execute(new ReleaseFinalPayData(
        checklistId: $checklist->id, releasedByUserId: $f['user']->id,
    )))->toThrow(ExitClearanceIncompleteException::class);

    foreach (['keys', 'assets', 'laptop', 'library', 'staff_advances'] as $code) {
        app(ClearExitChecklistItemAction::class)->execute(new ClearExitChecklistItemData(
            checklistId: $checklist->id, itemCode: $code, clearedByUserId: $f['user']->id,
        ));
    }

    $released = app(ReleaseFinalPayAction::class)->execute(new ReleaseFinalPayData(
        checklistId: $checklist->id, releasedByUserId: $f['user']->id,
    ));

    expect($released->final_pay_released_at)->not->toBeNull();
});

it('deactivates the linked account and revokes every token when a staff member exits (AC-PPL-04-007)', function (): void {
    $f = ppl04bFixture();
    $linkedUser = User::factory()->create(['status' => UserStatus::Active]);
    $staff = createTeachingStaffB($f);
    $staff->update(['user_id' => $linkedUser->id]);

    $token = $linkedUser->createToken('mobile');

    $exited = app(ProcessStaffExitAction::class)->execute(new ProcessStaffExitData(
        staffId: $staff->id, exitReason: 'resignation', exitedOn: now(), processedByUserId: $f['user']->id,
    ));

    expect($exited->status)->toBe('exited')
        ->and($exited->exited_on)->not->toBeNull();

    expect($linkedUser->fresh()->status)->toBe(UserStatus::Inactive)
        ->and(PersonalAccessToken::find($token->accessToken->id)->isRevoked())->toBeTrue();
});
