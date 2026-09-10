<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\Receipt;
use Modules\People\Domain\Actions\AcceptOfferAction;
use Modules\People\Domain\Actions\ConvertApplicationToStudentAction;
use Modules\People\Domain\Actions\CreateIntakeAction;
use Modules\People\Domain\Actions\DeclineApplicationAction;
use Modules\People\Domain\Actions\ExpireOfferAction;
use Modules\People\Domain\Actions\OfferApplicationAction;
use Modules\People\Domain\Actions\PayAcceptanceDepositAction;
use Modules\People\Domain\Actions\PayApplicationFeeAction;
use Modules\People\Domain\Actions\SubmitApplicationAction;
use Modules\People\Domain\DataObjects\AcceptOfferData;
use Modules\People\Domain\DataObjects\ConvertApplicationToStudentData;
use Modules\People\Domain\DataObjects\CreateIntakeData;
use Modules\People\Domain\DataObjects\DeclineApplicationData;
use Modules\People\Domain\DataObjects\ExpireOfferData;
use Modules\People\Domain\DataObjects\OfferApplicationData;
use Modules\People\Domain\DataObjects\PayAcceptanceDepositData;
use Modules\People\Domain\DataObjects\PayApplicationFeeData;
use Modules\People\Domain\DataObjects\SubmitApplicationData;
use Modules\People\Domain\Exceptions\IntakeCapacityExceededException;
use Modules\People\Models\Application;
use Modules\People\Models\FeeLiability;
use Modules\People\Models\Guardian;
use Modules\People\Models\Intake;
use Modules\People\Models\StudentGuardian;

/**
 * @return array<string, mixed>
 */
function ppl02Fixture(int $targetPlaces = 5, ?int $applicationFeeMinor = 1000, ?int $acceptanceDepositMinor = 20000): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $user = User::factory()->create();

    foreach (['admission' => 'ADM', 'application' => 'APP', 'receipt' => 'RCT', 'journal' => 'JNL'] as $documentType => $prefix) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $documentType, pattern: $prefix.'/{SEQ:6}', academicYearId: $year->id,
        ));
    }

    $bankAccount = Account::factory()->for($school)->create();
    $incomeAccount = Account::factory()->for($school)->income()->create();
    $refundableDeposits = Account::factory()->for($school)->create();
    $creditBalanceAccount = Account::factory()->for($school)->create();

    $intake = app(CreateIntakeAction::class)->execute(new CreateIntakeData(
        schoolId: $school->id, academicYearId: $year->id, name: 'Form 1 '.$year->id,
        gradeLevelId: $gradeLevel->id, opensOn: now()->subMonth(), closesOn: now()->addMonth(),
        targetPlaces: $targetPlaces, createdByUserId: $user->id,
        applicationFeeMinor: $applicationFeeMinor, applicationFeeCurrency: $applicationFeeMinor !== null ? 'USD' : null,
        acceptanceDepositMinor: $acceptanceDepositMinor, acceptanceDepositCurrency: $acceptanceDepositMinor !== null ? 'USD' : null,
    ));

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'section' => $section, 'gradeLevel' => $gradeLevel,
        'user' => $user, 'intake' => $intake, 'bankAccount' => $bankAccount, 'incomeAccount' => $incomeAccount,
        'refundableDeposits' => $refundableDeposits, 'creditBalanceAccount' => $creditBalanceAccount,
    ];
}

/**
 * @param  array<string, mixed>  $f
 * @param  array<string, mixed>  $overrides
 */
function submitPpl02Application(array $f, array $overrides = []): Application
{
    return app(SubmitApplicationAction::class)->execute(new SubmitApplicationData(
        schoolId: $f['school']->id,
        intakeId: $f['intake']->id,
        firstName: $overrides['firstName'] ?? 'Tadiwa',
        lastName: $overrides['lastName'] ?? 'Moyo',
        dateOfBirth: now()->subYears(12),
        gender: 'male',
        requestedGradeLevelId: $f['gradeLevel']->id,
        requestedEnrolmentType: 'FULL_TIME',
        requestedResidency: 'DAY',
        guardians: $overrides['guardians'] ?? [
            ['relationship' => 'mother', 'first_name' => 'Grace', 'last_name' => 'Moyo', 'primary_phone' => '+263771234567', 'is_primary_contact' => true, 'is_fee_responsible' => true],
        ],
        createdByUserId: $f['user']->id,
    ));
}

/**
 * @param  array<string, mixed>  $f
 */
function payAndOfferAndAccept(array $f, Application $application): Application
{
    if ($application->status === 'fee_pending') {
        app(PayApplicationFeeAction::class)->execute(new PayApplicationFeeData(
            applicationId: $application->id, termId: $f['term']->id, tenderType: 'cash',
            bankAccountId: $f['bankAccount']->id, incomeAccountId: $f['incomeAccount']->id, receivedByUserId: $f['user']->id,
        ));
    }

    app(OfferApplicationAction::class)->execute(new OfferApplicationData(
        applicationId: $application->id, offeredByUserId: $f['user']->id,
    ));

    return app(AcceptOfferAction::class)->execute(new AcceptOfferData(
        applicationId: $application->id, acceptedByUserId: $f['user']->id,
    ));
}

it('holds a fee-pending application invisible until the fee is paid, then submits it (AC-PPL-02-001/003)', function (): void {
    $f = ppl02Fixture();
    $application = submitPpl02Application($f);

    expect($application->status)->toBe('fee_pending')
        ->and($application->application_number)->not->toBeNull();

    $paid = app(PayApplicationFeeAction::class)->execute(new PayApplicationFeeData(
        applicationId: $application->id, termId: $f['term']->id, tenderType: 'cash',
        bankAccountId: $f['bankAccount']->id, incomeAccountId: $f['incomeAccount']->id, receivedByUserId: $f['user']->id,
    ));

    expect($paid->status)->toBe('submitted')
        ->and($paid->application_fee_receipt_id)->not->toBeNull();

    $journal = Journal::with('lines')->find(Receipt::find($paid->application_fee_receipt_id)->journal_id);
    expect($journal->lines->firstWhere('account_id', $f['incomeAccount']->id)->direction)->toBe('CR');
});

it('submits straight to submitted when the intake has no application fee', function (): void {
    $f = ppl02Fixture(applicationFeeMinor: null);
    $application = submitPpl02Application($f);

    expect($application->status)->toBe('submitted');
});

it('posts the acceptance deposit to Refundable Deposits, never to income, and reserves a place (AC-PPL-02-004)', function (): void {
    $f = ppl02Fixture();
    $application = submitPpl02Application($f);
    payAndOfferAndAccept($f, $application);

    $deposited = app(PayAcceptanceDepositAction::class)->execute(new PayAcceptanceDepositData(
        applicationId: $application->id, termId: $f['term']->id, tenderType: 'bank_transfer',
        bankAccountId: $f['bankAccount']->id, refundableDepositsAccountId: $f['refundableDeposits']->id,
        receivedByUserId: $f['user']->id,
    ));

    expect($deposited->status)->toBe('deposit_paid')
        ->and($f['intake']->fresh()->places_accepted)->toBe(1);

    $journal = Journal::with('lines')->find(Receipt::find($deposited->deposit_receipt_id)->journal_id);
    $depositLine = $journal->lines->firstWhere('account_id', $f['refundableDeposits']->id);

    expect($depositLine->direction)->toBe('CR')
        ->and($journal->lines->firstWhere('account_id', $f['incomeAccount']->id))->toBeNull();
});

it('refuses an offer expiry before offer_expires_at, then lapses it and promotes the next waitlisted applicant (BR-PPL-02-006)', function (): void {
    $f = ppl02Fixture();
    $application = submitPpl02Application($f);
    app(PayApplicationFeeAction::class)->execute(new PayApplicationFeeData(
        applicationId: $application->id, termId: $f['term']->id, tenderType: 'cash',
        bankAccountId: $f['bankAccount']->id, incomeAccountId: $f['incomeAccount']->id, receivedByUserId: $f['user']->id,
    ));
    app(OfferApplicationAction::class)->execute(new OfferApplicationData(applicationId: $application->id, offeredByUserId: $f['user']->id));

    expect(fn () => app(ExpireOfferAction::class)->execute(new ExpireOfferData($application->id)))
        ->toThrow(InvalidStateTransitionException::class);

    $waitlisted = submitPpl02Application($f, ['firstName' => 'Second', 'lastName' => 'Waitlisted', 'guardians' => [
        ['relationship' => 'father', 'first_name' => 'Second', 'last_name' => 'Waitlisted', 'primary_phone' => '+263779999999', 'is_fee_responsible' => true],
    ]]);
    $waitlisted->update(['status' => 'waitlisted', 'waitlist_position' => 1]);

    $application->update(['offer_expires_at' => now()->subDay()]);

    $expired = app(ExpireOfferAction::class)->execute(new ExpireOfferData($application->id));

    expect($expired->status)->toBe('expired')
        ->and($f['intake']->fresh()->places_offered)->toBe(1)
        ->and($waitlisted->fresh()->status)->toBe('offered');
});

it('refuses acceptance once the offer has expired', function (): void {
    $f = ppl02Fixture();
    $application = submitPpl02Application($f);
    app(PayApplicationFeeAction::class)->execute(new PayApplicationFeeData(
        applicationId: $application->id, termId: $f['term']->id, tenderType: 'cash',
        bankAccountId: $f['bankAccount']->id, incomeAccountId: $f['incomeAccount']->id, receivedByUserId: $f['user']->id,
    ));
    app(OfferApplicationAction::class)->execute(new OfferApplicationData(applicationId: $application->id, offeredByUserId: $f['user']->id));
    $application->update(['offer_expires_at' => now()->subDay()]);

    expect(fn () => app(AcceptOfferAction::class)->execute(new AcceptOfferData($application->id, $f['user']->id)))
        ->toThrow(InvalidStateTransitionException::class);
});

it('declines an application and, if it was offered, releases the offered place', function (): void {
    $f = ppl02Fixture();
    $application = submitPpl02Application($f);
    app(PayApplicationFeeAction::class)->execute(new PayApplicationFeeData(
        applicationId: $application->id, termId: $f['term']->id, tenderType: 'cash',
        bankAccountId: $f['bankAccount']->id, incomeAccountId: $f['incomeAccount']->id, receivedByUserId: $f['user']->id,
    ));
    app(OfferApplicationAction::class)->execute(new OfferApplicationData(applicationId: $application->id, offeredByUserId: $f['user']->id));

    $declined = app(DeclineApplicationAction::class)->execute(new DeclineApplicationData(
        applicationId: $application->id, reason: 'Family relocated', declinedByUserId: $f['user']->id,
    ));

    expect($declined->status)->toBe('declined')
        ->and($declined->declined_reason)->toBe('Family relocated')
        ->and($f['intake']->fresh()->places_offered)->toBe(0);

    expect(fn () => app(DeclineApplicationAction::class)->execute(new DeclineApplicationData($application->id, 'Again', $f['user']->id)))
        ->toThrow(InvalidStateTransitionException::class);
});

it('converts an application to a student with zero re-keying, linking guardians and converting the deposit to a learner credit (AC-PPL-02-007/BR-PPL-02-009/011)', function (): void {
    $f = ppl02Fixture();
    $application = submitPpl02Application($f);
    payAndOfferAndAccept($f, $application);
    app(PayAcceptanceDepositAction::class)->execute(new PayAcceptanceDepositData(
        applicationId: $application->id, termId: $f['term']->id, tenderType: 'bank_transfer',
        bankAccountId: $f['bankAccount']->id, refundableDepositsAccountId: $f['refundableDeposits']->id,
        receivedByUserId: $f['user']->id,
    ));

    $student = app(ConvertApplicationToStudentAction::class)->execute(new ConvertApplicationToStudentData(
        applicationId: $application->id, termId: $f['term']->id, convertedByUserId: $f['user']->id,
        creditBalanceAccountId: $f['creditBalanceAccount']->id,
    ));

    expect($student->first_name)->toBe('Tadiwa')
        ->and($student->last_name)->toBe('Moyo')
        ->and($student->grade_level_id)->toBe($f['gradeLevel']->id)
        ->and($student->section_id)->toBe($f['section']->id)
        ->and(StudentGuardian::where('student_id', $student->id)->count())->toBe(1);

    $guardian = Guardian::where('primary_phone', '+263771234567')->firstOrFail();
    $link = StudentGuardian::where('student_id', $student->id)->firstOrFail();
    expect($link->guardian_id)->toBe($guardian->id)
        ->and($link->is_fee_responsible)->toBeTrue();

    expect(FeeLiability::where('student_id', $student->id)->where('guardian_id', $guardian->id)->exists())->toBeTrue();

    $application = $application->fresh();
    expect($application->status)->toBe('enrolled')
        ->and($application->student_id)->toBe($student->id)
        ->and($application->converted_at)->not->toBeNull();

    $depositReceipt = Receipt::find($application->deposit_receipt_id);
    $conversionJournal = Journal::with('lines')
        ->where('journal_type', 'DEPOSIT_CONVERSION')
        ->where('source_id', $application->id)
        ->firstOrFail();

    $creditLine = $conversionJournal->lines->firstWhere('account_id', $f['creditBalanceAccount']->id);
    $reversedDepositLine = $conversionJournal->lines->firstWhere('account_id', $f['refundableDeposits']->id);

    expect($creditLine->direction)->toBe('CR')
        ->and($creditLine->subledger_type)->toBe('student')
        ->and($creditLine->subledger_id)->toBe($student->id)
        ->and((int) $creditLine->amount_minor)->toBe(20000)
        ->and($reversedDepositLine->direction)->toBe('DR');
});

it('reuses an existing guardian matched by phone instead of creating a duplicate', function (): void {
    $f = ppl02Fixture();
    $existing = Guardian::factory()->for($f['school'])->create(['primary_phone' => '+263771234567']);

    $application = submitPpl02Application($f);
    payAndOfferAndAccept($f, $application);
    app(PayAcceptanceDepositAction::class)->execute(new PayAcceptanceDepositData(
        applicationId: $application->id, termId: $f['term']->id, tenderType: 'bank_transfer',
        bankAccountId: $f['bankAccount']->id, refundableDepositsAccountId: $f['refundableDeposits']->id,
        receivedByUserId: $f['user']->id,
    ));

    $student = app(ConvertApplicationToStudentAction::class)->execute(new ConvertApplicationToStudentData(
        applicationId: $application->id, termId: $f['term']->id, convertedByUserId: $f['user']->id,
        creditBalanceAccountId: $f['creditBalanceAccount']->id,
    ));

    $link = StudentGuardian::where('student_id', $student->id)->firstOrFail();

    expect(Guardian::where('primary_phone', '+263771234567')->count())->toBe(1)
        ->and($link->guardian_id)->toBe($existing->id);
});

it('refuses conversion once the intake is full, unless overridden with a reason (BR-PPL-02-008)', function (): void {
    $f = ppl02Fixture(targetPlaces: 1);

    $first = submitPpl02Application($f);
    payAndOfferAndAccept($f, $first);
    app(PayAcceptanceDepositAction::class)->execute(new PayAcceptanceDepositData(
        applicationId: $first->id, termId: $f['term']->id, tenderType: 'bank_transfer',
        bankAccountId: $f['bankAccount']->id, refundableDepositsAccountId: $f['refundableDeposits']->id,
        receivedByUserId: $f['user']->id,
    ));

    // Converted while it still holds the intake's only reserved place —
    // this must succeed without an override.
    app(ConvertApplicationToStudentAction::class)->execute(new ConvertApplicationToStudentData(
        applicationId: $first->id, termId: $f['term']->id, convertedByUserId: $f['user']->id,
        creditBalanceAccountId: $f['creditBalanceAccount']->id,
    ));

    // A second applicant is allowed to pay a deposit speculatively even
    // though the intake is already full — BR-PPL-02-008 gates conversion,
    // not the deposit itself — but converting them is refused.
    $second = submitPpl02Application($f, ['firstName' => 'Second', 'lastName' => 'Applicant', 'guardians' => [
        ['relationship' => 'mother', 'first_name' => 'Second', 'last_name' => 'Applicant', 'primary_phone' => '+263775555555', 'is_fee_responsible' => true],
    ]]);
    payAndOfferAndAccept($f, $second);
    app(PayAcceptanceDepositAction::class)->execute(new PayAcceptanceDepositData(
        applicationId: $second->id, termId: $f['term']->id, tenderType: 'bank_transfer',
        bankAccountId: $f['bankAccount']->id, refundableDepositsAccountId: $f['refundableDeposits']->id,
        receivedByUserId: $f['user']->id,
    ));

    expect(fn () => app(ConvertApplicationToStudentAction::class)->execute(new ConvertApplicationToStudentData(
        applicationId: $second->id, termId: $f['term']->id, convertedByUserId: $f['user']->id,
        creditBalanceAccountId: $f['creditBalanceAccount']->id,
    )))->toThrow(IntakeCapacityExceededException::class);

    $converted = app(ConvertApplicationToStudentAction::class)->execute(new ConvertApplicationToStudentData(
        applicationId: $second->id, termId: $f['term']->id, convertedByUserId: $f['user']->id,
        creditBalanceAccountId: $f['creditBalanceAccount']->id,
        overrideCapacity: true, overrideReason: 'Head approved an over-enrolment for this cohort',
    ));

    expect($converted->id)->not->toBeNull();
});

it('refuses conversion from any status other than deposit_paid', function (): void {
    $f = ppl02Fixture();
    $application = submitPpl02Application($f);

    expect(fn () => app(ConvertApplicationToStudentAction::class)->execute(new ConvertApplicationToStudentData(
        applicationId: $application->id, termId: $f['term']->id, convertedByUserId: $f['user']->id,
        creditBalanceAccountId: $f['creditBalanceAccount']->id,
    )))->toThrow(InvalidStateTransitionException::class);
});
