<?php

use App\Models\User;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Registry\LearnerClearanceRegistry;
use Modules\Core\Models\File;
use Modules\People\Domain\Actions\AddHouseholdMemberAction;
use Modules\People\Domain\Actions\AddSponsorshipBeneficiaryAction;
use Modules\People\Domain\Actions\AddStaffQualificationAction;
use Modules\People\Domain\Actions\AdvanceEnquiryStageAction;
use Modules\People\Domain\Actions\AttachStudentDocumentAction;
use Modules\People\Domain\Actions\BuildAdmissionsFunnelAction;
use Modules\People\Domain\Actions\ChangeSponsorshipStatusAction;
use Modules\People\Domain\Actions\ChangeStudentStatusAction;
use Modules\People\Domain\Actions\CheckStudentDocumentExpiryAction;
use Modules\People\Domain\Actions\CreateEnquiryAction;
use Modules\People\Domain\Actions\CreateGuardianAction;
use Modules\People\Domain\Actions\CreateHouseholdAction;
use Modules\People\Domain\Actions\CreateSponsorshipAction;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\Actions\DecideGuardianContactUpdateAction;
use Modules\People\Domain\Actions\EndSponsorshipBeneficiaryAction;
use Modules\People\Domain\Actions\LinkSiblingsAction;
use Modules\People\Domain\Actions\LogEnquiryActivityAction;
use Modules\People\Domain\Actions\ProcessEntranceExamResultsAction;
use Modules\People\Domain\Actions\PublishEntranceExamResultsAction;
use Modules\People\Domain\Actions\RecordExamMarksAction;
use Modules\People\Domain\Actions\RecordInterviewOutcomeAction;
use Modules\People\Domain\Actions\RegisterExamCandidateAction;
use Modules\People\Domain\Actions\RemoveHouseholdMemberAction;
use Modules\People\Domain\Actions\RequestGuardianContactUpdateAction;
use Modules\People\Domain\Actions\ScheduleEntranceExamAction;
use Modules\People\Domain\Actions\ScheduleInterviewAction;
use Modules\People\Domain\Actions\TransferOutStudentAction;
use Modules\People\Domain\Actions\UnlinkSiblingsAction;
use Modules\People\Domain\Actions\VerifyStaffQualificationAction;
use Modules\People\Domain\Actions\VerifyStudentDocumentAction;
use Modules\People\Domain\DataObjects\AddHouseholdMemberData;
use Modules\People\Domain\DataObjects\AddSponsorshipBeneficiaryData;
use Modules\People\Domain\DataObjects\AddStaffQualificationData;
use Modules\People\Domain\DataObjects\AdvanceEnquiryStageData;
use Modules\People\Domain\DataObjects\AttachStudentDocumentData;
use Modules\People\Domain\DataObjects\ChangeSponsorshipStatusData;
use Modules\People\Domain\DataObjects\ChangeStudentStatusData;
use Modules\People\Domain\DataObjects\CreateEnquiryData;
use Modules\People\Domain\DataObjects\CreateGuardianData;
use Modules\People\Domain\DataObjects\CreateHouseholdData;
use Modules\People\Domain\DataObjects\CreateSponsorshipData;
use Modules\People\Domain\DataObjects\DecideGuardianContactUpdateData;
use Modules\People\Domain\DataObjects\EndSponsorshipBeneficiaryData;
use Modules\People\Domain\DataObjects\LinkSiblingsData;
use Modules\People\Domain\DataObjects\LogEnquiryActivityData;
use Modules\People\Domain\DataObjects\RecordExamMarksData;
use Modules\People\Domain\DataObjects\RecordInterviewOutcomeData;
use Modules\People\Domain\DataObjects\RegisterExamCandidateData;
use Modules\People\Domain\DataObjects\RemoveHouseholdMemberData;
use Modules\People\Domain\DataObjects\RequestGuardianContactUpdateData;
use Modules\People\Domain\DataObjects\ScheduleEntranceExamData;
use Modules\People\Domain\DataObjects\ScheduleInterviewData;
use Modules\People\Domain\DataObjects\TransferOutStudentData;
use Modules\People\Domain\DataObjects\UnlinkSiblingsData;
use Modules\People\Domain\DataObjects\VerifyStaffQualificationData;
use Modules\People\Domain\DataObjects\VerifyStudentDocumentData;
use Modules\People\Domain\Events\StudentDocumentExpiring;
use Modules\People\Domain\Exceptions\LearnerClearanceIncompleteException;
use Modules\People\Domain\Support\PhoneNumberNormaliser;
use Modules\People\Models\FeeLiability;
use Modules\People\Models\HouseholdMember;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\People\Models\StudentSibling;
use Modules\People\Models\StudentTimelineEvent;

/**
 * Book C completion: the PPL-01/02/03/04 features whose tables did not exist.
 * Reuses `studentFixture()`/`createStudentData()` and `ppl02Fixture()`, so run
 * the module directory.
 */
function bcStudent(array $f, string $first = 'Tinashe', array $o = []): Student
{
    return app(CreateStudentAction::class)->execute(createStudentData($f, ['firstName' => $first, 'skipDuplicateCheck' => true] + $o));
}

function bcFile(array $f): File
{
    return File::factory()->create(['school_id' => $f['school']->id]);
}

it('verifies a staff qualification only against a certificate and never by the holder', function (): void {
    $f = studentFixture();
    $holderUser = User::factory()->create();
    $staff = Staff::factory()->for($f['school'])->create(['user_id' => $holderUser->id]);

    $q = app(AddStaffQualificationAction::class)->execute(new AddStaffQualificationData($f['school']->id, $staff->id, 'degree', 'BEd Maths', 'UZ', yearObtained: 2015, subjects: ['Mathematics']));

    expect(fn () => app(VerifyStaffQualificationAction::class)->execute(new VerifyStaffQualificationData($q->id, $f['user']->id)))->toThrow(InvalidArgumentException::class, 'certificate');

    $q->update(['certificate_file_id' => bcFile($f)->id]);

    expect(fn () => app(VerifyStaffQualificationAction::class)->execute(new VerifyStaffQualificationData($q->id, $holderUser->id)))->toThrow(InvalidArgumentException::class, 'own');
    expect(app(VerifyStaffQualificationAction::class)->execute(new VerifyStaffQualificationData($q->id, $f['user']->id))->is_verified)->toBeTrue();
    expect(fn () => app(AddStaffQualificationAction::class)->execute(new AddStaffQualificationData($f['school']->id, $staff->id, 'degree', 'X', 'Y', yearObtained: 2999)))->toThrow(InvalidArgumentException::class);
});

it('attaches learner documents, refuses to verify an expired one, and alerts at the warning thresholds (BR-PPL-01-021)', function (): void {
    Event::fake([StudentDocumentExpiring::class]);
    $f = studentFixture();
    $student = bcStudent($f);

    $permit = app(AttachStudentDocumentAction::class)->execute(new AttachStudentDocumentData($f['school']->id, $student->id, 'study_permit', bcFile($f)->id, $f['user']->id, expiresOn: now()->addDays(30)));
    $old = app(AttachStudentDocumentAction::class)->execute(new AttachStudentDocumentData($f['school']->id, $student->id, 'passport', bcFile($f)->id, $f['user']->id, expiresOn: now()->subDay()));

    expect(fn () => app(AttachStudentDocumentAction::class)->execute(new AttachStudentDocumentData($f['school']->id, $student->id, 'selfie', bcFile($f)->id, $f['user']->id)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(VerifyStudentDocumentAction::class)->execute(new VerifyStudentDocumentData($old->id, $f['user']->id)))->toThrow(InvalidStateTransitionException::class);

    expect(app(VerifyStudentDocumentAction::class)->execute(new VerifyStudentDocumentData($permit->id, $f['user']->id, originalSighted: true))->is_original_sighted)->toBeTrue();

    $found = app(CheckStudentDocumentExpiryAction::class)->execute($f['school']->id);
    expect(collect($found)->pluck('daysRemaining')->all())->toContain(30);
    Event::assertDispatched(StudentDocumentExpiring::class, fn ($e): bool => $e->daysRemaining === 30);
});

it('stores sibling links in both directions, refuses duplicates and self links, and removes both on unlink (BR-PPL-01-018)', function (): void {
    $f = studentFixture();
    $a = bcStudent($f, 'Alpha');
    $b = bcStudent($f, 'Bravo');

    app(LinkSiblingsAction::class)->execute(new LinkSiblingsData($a->id, $b->id, 'full', $f['user']->id));

    expect(StudentSibling::query()->count())->toBe(2)
        ->and(fn () => app(LinkSiblingsAction::class)->execute(new LinkSiblingsData($b->id, $a->id, 'full', $f['user']->id)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(LinkSiblingsAction::class)->execute(new LinkSiblingsData($a->id, $a->id, 'full', $f['user']->id)))->toThrow(InvalidArgumentException::class);

    app(UnlinkSiblingsAction::class)->execute(new UnlinkSiblingsData($a->id, $b->id));

    expect(StudentSibling::query()->count())->toBe(0);
});

it('lists every failed clearance on transfer-out, lets the head override with a reason, and records it on the timeline (BR-PPL-01-014)', function (): void {
    $f = studentFixture();
    $student = bcStudent($f);
    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData($student->id, 'active', $f['user']->id));
    LearnerClearanceRegistry::register('test_books', fn (int $s, int $id): array => ['3 textbooks not returned']);

    try {
        $data = fn (?string $override) => new TransferOutStudentData($student->id, $f['user']->id, now(), 'St Mary\'s', clearanceOverrideReason: $override);

        expect(fn () => app(TransferOutStudentAction::class)->execute($data(null)))->toThrow(LearnerClearanceIncompleteException::class, '3 textbooks')
            ->and(fn () => app(TransferOutStudentAction::class)->execute($data('short')))->toThrow(LearnerClearanceIncompleteException::class);

        $out = app(TransferOutStudentAction::class)->execute($data('Parent settled the books in cash; receipt on file.'));

        expect($out->status)->toBe('transferred')
            ->and(StudentTimelineEvent::where('student_id', $student->id)->where('event_type', 'transferred_out')->value('summary'))->toContain('Cleared by override');
    } finally {
        LearnerClearanceRegistry::forget('test_books');
    }
});

it('feeds the learner timeline from status changes without ever blocking them', function (): void {
    $f = studentFixture();
    $student = bcStudent($f);
    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData($student->id, 'active', $f['user']->id));
    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData($student->id, 'suspended', $f['user']->id));

    expect(StudentTimelineEvent::where('student_id', $student->id)->pluck('event_type')->all())->toContain('enrolled', 'status_active', 'status_suspended')
        ->and(StudentTimelineEvent::where('event_type', 'status_suspended')->value('severity'))->toBe('warning');
});

it('keeps a learner in one household at a time and dates membership (BR-PPL-03-016)', function (): void {
    $f = studentFixture();
    $student = bcStudent($f);
    $one = app(CreateHouseholdAction::class)->execute(new CreateHouseholdData($f['school']->id, 'Moyo Family'));
    $two = app(CreateHouseholdAction::class)->execute(new CreateHouseholdData($f['school']->id, 'Ncube Family'));

    app(AddHouseholdMemberAction::class)->execute(new AddHouseholdMemberData($one->id, 'student', $student->id));

    expect(fn () => app(AddHouseholdMemberAction::class)->execute(new AddHouseholdMemberData($two->id, 'student', $student->id)))->toThrow(InvalidArgumentException::class, 'another household');

    $left = app(RemoveHouseholdMemberAction::class)->execute(new RemoveHouseholdMemberData($one->id, 'student', $student->id));
    expect($left->left_on)->not->toBeNull();

    app(AddHouseholdMemberAction::class)->execute(new AddHouseholdMemberData($two->id, 'student', $student->id));
    expect(HouseholdMember::query()->where('member_id', $student->id)->whereNull('left_on')->count())->toBe(1);
});

it('bills a sponsor through a real fee liability, refuses a commitment past the budget and an excess beneficiary, and ends support only by decision (BR-PPL-03-018/019/020)', function (): void {
    $f = studentFixture();
    $sponsor = app(CreateGuardianAction::class)->execute(new CreateGuardianData($f['school']->id, 'organisation', $f['user']->id, organisationName: 'Diocese Trust'));
    $parent = app(CreateGuardianAction::class)->execute(new CreateGuardianData($f['school']->id, 'individual', $f['user']->id, firstName: 'Grace', lastName: 'Moyo'));

    expect(fn () => app(CreateSponsorshipAction::class)->execute(new CreateSponsorshipData($f['school']->id, $parent->id, 'Bad', 'full', now(), $f['user']->id)))->toThrow(InvalidArgumentException::class, 'organisation');

    $sponsorship = app(CreateSponsorshipAction::class)->execute(new CreateSponsorshipData($f['school']->id, $sponsor->id, 'Orphan Support', 'full', now(), $f['user']->id, budgetMinor: 100000, budgetCurrency: 'USD', maxBeneficiaries: 1));
    app(ChangeSponsorshipStatusAction::class)->execute(new ChangeSponsorshipStatusData($sponsorship->id, 'active'));

    $a = bcStudent($f, 'Alpha');
    $b = bcStudent($f, 'Bravo');

    expect(fn () => app(AddSponsorshipBeneficiaryAction::class)->execute(new AddSponsorshipBeneficiaryData($sponsorship->id, $a->id, $f['user']->id, commitmentMinor: 150000)))->toThrow(InvalidArgumentException::class, 'exceed the sponsorship budget');

    $beneficiary = app(AddSponsorshipBeneficiaryAction::class)->execute(new AddSponsorshipBeneficiaryData($sponsorship->id, $a->id, $f['user']->id, commitmentMinor: 60000, performanceCondition: 'Maintain 60%'));
    $liability = FeeLiability::findOrFail($beneficiary->fee_liability_id);

    expect($liability->guardian_id)->toBe($sponsor->id)->and($liability->share_type)->toBe('full_component')
        ->and($sponsorship->fresh()->committed_minor)->toBe(60000)
        ->and(fn () => app(AddSponsorshipBeneficiaryAction::class)->execute(new AddSponsorshipBeneficiaryData($sponsorship->id, $b->id, $f['user']->id)))->toThrow(InvalidArgumentException::class, 'limited to 1');

    $ended = app(EndSponsorshipBeneficiaryAction::class)->execute(new EndSponsorshipBeneficiaryData($beneficiary->id));

    expect($ended->status)->toBe('ended')->and($liability->fresh()->is_active)->toBeFalse();
});

it('normalises phones to E.164 and holds a guardian\'s contact change until staff approve it (BR-PPL-03-013/021)', function (): void {
    expect(PhoneNumberNormaliser::normalise('0771 234 567'))->toBe('+263771234567')
        ->and(PhoneNumberNormaliser::normalise('+27 82 123 4567'))->toBe('+27821234567')
        ->and(fn () => PhoneNumberNormaliser::normalise('12'))->toThrow(InvalidArgumentException::class);

    $f = studentFixture();
    $guardian = app(CreateGuardianAction::class)->execute(new CreateGuardianData($f['school']->id, 'individual', $f['user']->id, firstName: 'Grace', lastName: 'Moyo', primaryPhone: '0771111111'));
    expect($guardian->primary_phone)->toBe('+263771111111');

    $update = app(RequestGuardianContactUpdateAction::class)->execute(new RequestGuardianContactUpdateData($guardian->id, ['primary_phone' => '0772222222', 'email' => 'grace@example.com']));

    expect($guardian->fresh()->primary_phone)->toBe('+263771111111')
        ->and(fn () => app(RequestGuardianContactUpdateAction::class)->execute(new RequestGuardianContactUpdateData($guardian->id, ['email' => 'x@example.com'])))->toThrow(InvalidArgumentException::class, 'already waiting')
        ->and(fn () => app(RequestGuardianContactUpdateAction::class)->execute(new RequestGuardianContactUpdateData($guardian->id, ['national_registration_no' => '1'])))->toThrow(InvalidArgumentException::class);

    app(DecideGuardianContactUpdateAction::class)->execute(new DecideGuardianContactUpdateData($update->id, $f['user']->id, true));

    expect($guardian->fresh()->primary_phone)->toBe('+263772222222')->and($guardian->fresh()->email)->toBe('grace@example.com')
        ->and(fn () => app(DecideGuardianContactUpdateAction::class)->execute(new DecideGuardianContactUpdateData($update->id, $f['user']->id, false)))->toThrow(InvalidStateTransitionException::class);
});

it('runs an enquiry from first contact to a lost reason and reports the funnel', function (): void {
    $f = ppl02Fixture();

    $e = app(CreateEnquiryAction::class)->execute(new CreateEnquiryData($f['school']->id, 'walk_in', 'Mrs Ncube', enquirerPhone: '0771234567', intakeId: $f['intake']->id));
    expect($e->enquirer_phone)->toBe('+263771234567')
        ->and(fn () => app(CreateEnquiryAction::class)->execute(new CreateEnquiryData($f['school']->id, 'walk_in', 'No contact')))->toThrow(InvalidArgumentException::class);

    app(LogEnquiryActivityAction::class)->execute(new LogEnquiryActivityData($e->id, 'call', 'Called, wants a visit', $f['user']->id, nextFollowUpOn: now()->addDays(3)));
    expect($e->fresh()->stage)->toBe('contacted')
        ->and(fn () => app(AdvanceEnquiryStageAction::class)->execute(new AdvanceEnquiryStageData($e->id, 'new')))->toThrow(InvalidStateTransitionException::class)
        ->and(fn () => app(AdvanceEnquiryStageAction::class)->execute(new AdvanceEnquiryStageData($e->id, 'lost')))->toThrow(InvalidArgumentException::class);

    app(AdvanceEnquiryStageAction::class)->execute(new AdvanceEnquiryStageData($e->id, 'lost', 'fees'));

    $funnel = app(BuildAdmissionsFunnelAction::class)->execute($f['intake']->id);
    expect($funnel['enquiries'])->toBe(1)->and($funnel['lost'])->toBe(['fees' => 1]);
});

it('seats applicants for an entrance exam, ranks them by weighted percentage and moves their applications on (PPL-02)', function (): void {
    $f = ppl02Fixture();
    $a = submitPpl02Application($f, ['firstName' => 'Anna']);
    $b = submitPpl02Application($f, ['firstName' => 'Ben', 'guardians' => [['relationship' => 'father', 'first_name' => 'Tom', 'last_name' => 'Ben', 'primary_phone' => '+263772000000', 'is_primary_contact' => true, 'is_fee_responsible' => true]]]);
    foreach ([$a, $b] as $application) {
        $application->update(['status' => 'submitted']);
    }

    expect(fn () => app(ScheduleEntranceExamAction::class)->execute(new ScheduleEntranceExamData($f['school']->id, $f['intake']->id, 'Bad', now()->addWeek(), '09:00', [['subject' => 'Maths', 'max_mark' => 100, 'weight' => 60]])))->toThrow(InvalidArgumentException::class, 'total 100');

    $exam = app(ScheduleEntranceExamAction::class)->execute(new ScheduleEntranceExamData($f['school']->id, $f['intake']->id, 'Form 1 entrance', now()->addWeek(), '09:00', [['subject' => 'Maths', 'max_mark' => 100, 'weight' => 50], ['subject' => 'English', 'max_mark' => 50, 'weight' => 50]], capacity: 2));

    $ca = app(RegisterExamCandidateAction::class)->execute(new RegisterExamCandidateData($exam->id, $a->id));
    $cb = app(RegisterExamCandidateAction::class)->execute(new RegisterExamCandidateData($exam->id, $b->id));
    expect(fn () => app(RegisterExamCandidateAction::class)->execute(new RegisterExamCandidateData($exam->id, $a->id)))->toThrow(InvalidArgumentException::class);

    expect(fn () => app(RecordExamMarksAction::class)->execute(new RecordExamMarksData($ca->id, true, ['Maths' => 101, 'English' => 10])))->toThrow(InvalidArgumentException::class);
    app(RecordExamMarksAction::class)->execute(new RecordExamMarksData($ca->id, true, ['Maths' => 80, 'English' => 40]));
    expect(fn () => app(ProcessEntranceExamResultsAction::class)->execute($exam->id))->toThrow(InvalidArgumentException::class, 'every candidate');
    app(RecordExamMarksAction::class)->execute(new RecordExamMarksData($cb->id, false));

    app(ProcessEntranceExamResultsAction::class)->execute($exam->id);

    expect((float) $ca->fresh()->percentage)->toBe(80.0)->and($ca->fresh()->rank_in_exam)->toBe(1)->and($cb->fresh()->rank_in_exam)->toBeNull()
        ->and($a->fresh()->status)->toBe('exam_completed')
        ->and(app(PublishEntranceExamResultsAction::class)->execute($exam->id)->status)->toBe('published')
        ->and(fn () => app(RecordExamMarksAction::class)->execute(new RecordExamMarksData($ca->id, true, ['Maths' => 1, 'English' => 1])))->toThrow(InvalidArgumentException::class);
});

it('schedules an interview with a panel from this school and records the outcome (PPL-02)', function (): void {
    $f = ppl02Fixture();
    $application = submitPpl02Application($f);
    $application->update(['status' => 'submitted']);
    $f['school']->users()->attach($f['user'], ['status' => 'active']);

    expect(fn () => app(ScheduleInterviewAction::class)->execute(new ScheduleInterviewData($f['school']->id, $application->id, now()->addDay(), [User::factory()->create()->id])))->toThrow(InvalidArgumentException::class, 'panel');

    $interview = app(ScheduleInterviewAction::class)->execute(new ScheduleInterviewData($f['school']->id, $application->id, now()->addDay(), [$f['user']->id], 'Head\'s office'));

    expect(fn () => app(RecordInterviewOutcomeAction::class)->execute(new RecordInterviewOutcomeData($interview->id, true, ['Attitude' => 80], 'maybe')))->toThrow(InvalidArgumentException::class);

    $done = app(RecordInterviewOutcomeAction::class)->execute(new RecordInterviewOutcomeData($interview->id, true, ['Attitude' => 80, 'Maths' => 70], 'accept'));

    expect((float) $done->total_score)->toBe(150.0)->and($application->fresh()->status)->toBe('interview_completed');
});
