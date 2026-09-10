<?php

use App\Models\User;
use Modules\Compliance\Domain\Actions\CheckRetentionScheduleCoverageAction;
use Modules\Compliance\Domain\Actions\CompileSubjectAccessResponseAction;
use Modules\Compliance\Domain\Actions\CreateConsentTypeAction;
use Modules\Compliance\Domain\Actions\CreatePrivacyNoticeAction;
use Modules\Compliance\Domain\Actions\CreateRetentionScheduleAction;
use Modules\Compliance\Domain\Actions\DisposeQueuedRecordAction;
use Modules\Compliance\Domain\Actions\EnqueueDueDisposalsAction;
use Modules\Compliance\Domain\Actions\ReceiveSubjectAccessRequestAction;
use Modules\Compliance\Domain\Actions\RecordBreachNotificationDecisionAction;
use Modules\Compliance\Domain\Actions\RecordConsentAction;
use Modules\Compliance\Domain\Actions\RecordDataBreachAction;
use Modules\Compliance\Domain\Actions\RegisterProcessingActivityAction;
use Modules\Compliance\Domain\Actions\RegisterThirdPartyProcessorAction;
use Modules\Compliance\Domain\Actions\ReviewDisposalQueueItemAction;
use Modules\Compliance\Domain\Actions\VerifyRequesterIdentityAction;
use Modules\Compliance\Domain\Actions\WithdrawConsentAction;
use Modules\Compliance\Domain\DataObjects\CreateConsentTypeData;
use Modules\Compliance\Domain\DataObjects\CreatePrivacyNoticeData;
use Modules\Compliance\Domain\DataObjects\CreateRetentionScheduleData;
use Modules\Compliance\Domain\DataObjects\ReceiveSubjectAccessRequestData;
use Modules\Compliance\Domain\DataObjects\RecordBreachNotificationDecisionData;
use Modules\Compliance\Domain\DataObjects\RecordConsentData;
use Modules\Compliance\Domain\DataObjects\RecordDataBreachData;
use Modules\Compliance\Domain\DataObjects\RegisterProcessingActivityData;
use Modules\Compliance\Domain\DataObjects\RegisterThirdPartyProcessorData;
use Modules\Compliance\Domain\DataObjects\ReviewDisposalQueueItemData;
use Modules\Compliance\Domain\DataObjects\WithdrawConsentData;
use Modules\Compliance\Domain\Exceptions\ConsentNotWithdrawableException;
use Modules\Compliance\Domain\Exceptions\IdentityNotVerifiedException;
use Modules\Compliance\Models\ThirdPartyProcessor;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\DeclineApplicationAction;
use Modules\People\Domain\DataObjects\DeclineApplicationData;
use Modules\People\Models\Application;
use Modules\People\Models\Intake;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * @return array<string, mixed>
 */
function cmp03Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $user = User::factory()->create();

    return compact('school', 'user');
}

it('records consent capturing the privacy notice version in force at the time (BR-CMP-03-001/002)', function (): void {
    $f = cmp03Fixture();
    $student = Student::factory()->for($f['school'])->create();
    $guardian = StudentGuardian::factory()->for($f['school'])->for($student)->create();
    $type = app(CreateConsentTypeAction::class)->execute(new CreateConsentTypeData(
        schoolId: $f['school']->id, code: 'photography', name: 'Photography', description: 'Photo use.',
        lawfulBasis: 'consent', appliesTo: 'student',
    ));

    app(CreatePrivacyNoticeAction::class)->execute(new CreatePrivacyNoticeData(
        schoolId: $f['school']->id, version: 'v3', title: 'Notice v3', content: '...',
        effectiveFrom: now()->subMonth()->toDateString(), createdByUserId: $f['user']->id,
    ));

    $consent = app(RecordConsentAction::class)->execute(new RecordConsentData(
        schoolId: $f['school']->id, consentTypeId: $type->id, subjectType: 'student', subjectId: $student->id,
        grantedByType: 'guardian', grantedById: $guardian->guardian_id, granted: true, method: 'portal',
    ));

    expect($consent->notice_version)->toBe('v3')
        ->and($consent->granted)->toBeTrue();
});

it('withdraws consent immediately, refusing when the type is not withdrawable (AC-CMP-03-001, BR-CMP-03-004)', function (): void {
    $f = cmp03Fixture();
    $student = Student::factory()->for($f['school'])->create();
    $guardian = StudentGuardian::factory()->for($f['school'])->for($student)->create();

    $withdrawableType = app(CreateConsentTypeAction::class)->execute(new CreateConsentTypeData(
        schoolId: $f['school']->id, code: 'photography', name: 'Photography', description: 'Photo use.',
        lawfulBasis: 'consent', appliesTo: 'student',
    ));
    $legalType = app(CreateConsentTypeAction::class)->execute(new CreateConsentTypeData(
        schoolId: $f['school']->id, code: 'medical_treatment', name: 'Emergency Medical Treatment',
        description: 'Vital interest.', lawfulBasis: 'vital_interest', appliesTo: 'student', isWithdrawable: false,
    ));

    $consent = app(RecordConsentAction::class)->execute(new RecordConsentData(
        schoolId: $f['school']->id, consentTypeId: $withdrawableType->id, subjectType: 'student', subjectId: $student->id,
        grantedByType: 'guardian', grantedById: $guardian->guardian_id, granted: true, method: 'portal',
    ));
    $withdrawn = app(WithdrawConsentAction::class)->execute(new WithdrawConsentData(
        consentId: $consent->id, withdrawnByUserId: $f['user']->id, withdrawalReason: 'Guardian request.',
    ));
    expect($withdrawn->withdrawn_at)->not->toBeNull();

    $legalConsent = app(RecordConsentAction::class)->execute(new RecordConsentData(
        schoolId: $f['school']->id, consentTypeId: $legalType->id, subjectType: 'student', subjectId: $student->id,
        grantedByType: 'guardian', grantedById: $guardian->guardian_id, granted: true, method: 'portal',
    ));

    expect(fn () => app(WithdrawConsentAction::class)->execute(new WithdrawConsentData(
        consentId: $legalConsent->id, withdrawnByUserId: $f['user']->id, withdrawalReason: 'Guardian request.',
    )))->toThrow(ConsentNotWithdrawableException::class);
});

it('reports a table with no active retention schedule (AC-CMP-03-006)', function (): void {
    $f = cmp03Fixture();

    $coverage = app(CheckRetentionScheduleCoverageAction::class)->execute($f['school']->id);
    expect($coverage['uncovered'])->toContain('students');

    app(CreateRetentionScheduleAction::class)->execute(new CreateRetentionScheduleData(
        schoolId: $f['school']->id, recordClass: 'academic_record', tableNames: ['students'],
        retentionYears: '7.00', retentionTrigger: 'learner_exit', disposalMethod: 'anonymise',
        legalBasis: 'Cyber and Data Protection Act [Chapter 12:07]',
    ));

    $coverage = app(CheckRetentionScheduleCoverageAction::class)->execute($f['school']->id);
    expect($coverage['uncovered'])->not->toContain('students')
        ->and($coverage['covered'])->toContain('students');
});

it('queues an eligible record for review rather than disposing it automatically, then disposes only once approved (AC-CMP-03-005)', function (): void {
    $f = cmp03Fixture();
    $intake = Intake::factory()->for($f['school'])->create();
    $application = Application::factory()->for($f['school'])->for($intake)->create(['status' => 'submitted']);
    app(DeclineApplicationAction::class)->execute(new DeclineApplicationData(
        applicationId: $application->id, reason: 'Place not available.', declinedByUserId: $f['user']->id,
    ));
    $application->refresh();
    $application->forceFill(['updated_at' => now()->subYears(4)])->saveQuietly();

    app(CreateRetentionScheduleAction::class)->execute(new CreateRetentionScheduleData(
        schoolId: $f['school']->id, recordClass: 'application_unsuccessful', tableNames: ['applications'],
        retentionYears: '3.00', retentionTrigger: 'record_created', disposalMethod: 'anonymise',
        legalBasis: 'Cyber and Data Protection Act [Chapter 12:07]',
    ));

    $queued = app(EnqueueDueDisposalsAction::class)->execute($f['school']->id);
    expect($queued)->toHaveCount(1);
    $item = $queued->first();
    expect($item->review_status)->toBe('pending_review');

    expect(fn () => app(DisposeQueuedRecordAction::class)->execute($item->id))->toThrow(InvalidStateTransitionException::class);

    app(ReviewDisposalQueueItemAction::class)->execute(new ReviewDisposalQueueItemData(
        itemId: $item->id, reviewedByUserId: $f['user']->id, decision: 'approved',
    ));
    $disposed = app(DisposeQueuedRecordAction::class)->execute($item->id);

    expect($disposed->review_status)->toBe('disposed')
        ->and($disposed->disposed_at)->not->toBeNull();
    expect($application->fresh()->first_name)->toBe('Redacted');
});

it('sets a statutory due date and refuses to compile a response before identity is verified (AC-CMP-03-002/003)', function (): void {
    $f = cmp03Fixture();
    $student = Student::factory()->for($f['school'])->create();

    $request = app(ReceiveSubjectAccessRequestAction::class)->execute(new ReceiveSubjectAccessRequestData(
        schoolId: $f['school']->id, requestType: 'access', subjectType: 'student', subjectId: $student->id,
        requesterName: 'Jane Guardian', scopeDescription: 'All personal data held about the learner.',
    ));

    expect($request->due_by)->not->toBeNull()
        ->and($request->identity_verified)->toBeFalse();

    expect(fn () => app(CompileSubjectAccessResponseAction::class)->execute($request->id))
        ->toThrow(IdentityNotVerifiedException::class);

    app(VerifyRequesterIdentityAction::class)->execute($request->id, $f['user']->id, 'national_id_checked');

    $compiled = app(CompileSubjectAccessResponseAction::class)->execute($request->id);

    expect($compiled['bio_data']['first_name'])->toBe($student->first_name)
        ->and($compiled['excluded'])->toContain('safeguarding_records')
        ->and($compiled['excluded'])->toContain('medical_records');
});

it('escalates a breach involving minors to the resolved head and safeguarding lead (AC-CMP-03-004)', function (): void {
    $f = cmp03Fixture();
    $head = Staff::factory()->for($f['school'])->create(['primary_phone' => '+263771111111', 'work_email' => 'head@example.com']);

    app(SetSettingValueAction::class)->execute(new SetSettingValueData(
        key: 'compliance.head_staff_id', scopeType: SettingScope::School, scopeId: $f['school']->id,
        value: $head->id, setByUserId: $f['user']->id,
    ));

    $breach = app(RecordDataBreachAction::class)->execute(new RecordDataBreachData(
        schoolId: $f['school']->id, breachType: 'unauthorised_access', description: 'Medical records exposed.',
        dataCategories: ['medical_records'], severity: 'high', includesMinors: true,
        reportedByUserId: $f['user']->id, subjectsAffected: 40,
    ));

    expect($breach->includes_minors)->toBeTrue()
        ->and($breach->status)->toBe('detected');

    $decided = app(RecordBreachNotificationDecisionAction::class)->execute(new RecordBreachNotificationDecisionData(
        breachId: $breach->id, authorityNotified: false, subjectsNotified: false,
    ));

    expect($decided->authority_notified)->toBeFalse()
        ->and($decided->status)->toBe('notified');
});

it('registers a processing activity and flags a cross-border third-party processor (BR-CMP-03-013/014)', function (): void {
    $f = cmp03Fixture();

    $entry = app(RegisterProcessingActivityAction::class)->execute(new RegisterProcessingActivityData(
        schoolId: $f['school']->id, activityName: 'SMS notifications', purpose: 'Guardian alerts.',
        lawfulBasis: 'legitimate_interest', dataCategories: ['contact_details'], subjectCategories: ['guardian'],
        owningModule: 'CORE-09', involvesMinors: false,
    ));
    expect($entry->owning_module)->toBe('CORE-09');

    $processor = app(RegisterThirdPartyProcessorAction::class)->execute(new RegisterThirdPartyProcessorData(
        schoolId: $f['school']->id, name: 'Overseas SMS Gateway', processorType: 'sms',
        dataShared: ['guardian_phone_number'], purpose: 'SMS delivery.', country: 'ZA',
    ));

    expect($processor->isCrossBorder())->toBeTrue();

    $local = ThirdPartyProcessor::factory()->for($f['school'])->create(['country' => 'ZW']);
    expect($local->isCrossBorder())->toBeFalse();
});
