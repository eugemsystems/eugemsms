<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\ApproveFeeWaiverAction;
use Modules\Finance\Domain\Actions\ApprovePaymentPlanAction;
use Modules\Finance\Domain\Actions\CancelPaymentPlanAction;
use Modules\Finance\Domain\Actions\CheckPaymentPlanBreachesAction;
use Modules\Finance\Domain\Actions\CheckReportGateAction;
use Modules\Finance\Domain\Actions\CreatePaymentPlanAction;
use Modules\Finance\Domain\Actions\GenerateAgedDebtorsReportAction;
use Modules\Finance\Domain\Actions\GenerateStatementAction;
use Modules\Finance\Domain\Actions\GrantReportGateOverrideAction;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\Actions\RecordDebtorChaseNoteAction;
use Modules\Finance\Domain\Actions\RecordPaymentPlanInstalmentPaymentAction;
use Modules\Finance\Domain\Actions\RejectFeeWaiverAction;
use Modules\Finance\Domain\Actions\RequestFeeWaiverAction;
use Modules\Finance\Domain\DataObjects\ApproveFeeWaiverData;
use Modules\Finance\Domain\DataObjects\ApprovePaymentPlanData;
use Modules\Finance\Domain\DataObjects\CancelPaymentPlanData;
use Modules\Finance\Domain\DataObjects\CheckReportGateData;
use Modules\Finance\Domain\DataObjects\CreatePaymentPlanData;
use Modules\Finance\Domain\DataObjects\GenerateAgedDebtorsReportData;
use Modules\Finance\Domain\DataObjects\GenerateStatementData;
use Modules\Finance\Domain\DataObjects\GrantReportGateOverrideData;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\DataObjects\RecordDebtorChaseNoteData;
use Modules\Finance\Domain\DataObjects\RecordPaymentPlanInstalmentPaymentData;
use Modules\Finance\Domain\DataObjects\RejectFeeWaiverData;
use Modules\Finance\Domain\DataObjects\RequestFeeWaiverData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\FeeWaiver;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceLine;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User}
 */
function fin03Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}',
    ));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{SEQ:4}', academicYearId: $year->id,
    ));

    return ['school' => $school, 'year' => $year, 'term' => $term, 'user' => User::factory()->create()];
}

/**
 * @param  array{school: School, year: AcademicYear, term: Term, user: User}  $f
 */
function fin03Student(array $f): Student
{
    return app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        firstName: 'Tendai',
        lastName: 'Moyo',
        dateOfBirth: now()->subYears(14),
        gender: 'male',
        enrolmentType: 'FULL_TIME',
        residency: 'DAY',
        sectionId: SchoolSection::factory()->for($f['school'])->create()->id,
        gradeLevelId: GradeLevel::factory()->for($f['school'])->create()->id,
        entryCohortYear: (int) now()->year,
        createdByUserId: $f['user']->id,
        skipDuplicateCheck: true,
    ));
}

it('requests a waiver as pending, then approves it — posting a journal and reducing the invoice balance (BR-FIN-03-012/013)', function (): void {
    $f = fin03Fixture();
    $student = fin03Student($f);
    $income = Account::factory()->for($f['school'])->income()->create();
    $debtor = Account::factory()->for($f['school'])->controlAccount('student')->create();
    $badDebt = Account::factory()->for($f['school'])->expense()->create();
    $component = FeeComponent::factory()->for($f['school'])->create(['income_account_id' => $income->id, 'debtor_account_id' => $debtor->id]);

    $invoice = Invoice::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'student_id' => $student->id,
        'gross_minor' => 10000, 'net_minor' => 10000, 'balance_minor' => 10000, 'currency' => 'USD',
    ]);
    InvoiceLine::factory()->for($f['school'])->create([
        'invoice_id' => $invoice->id, 'component_id' => $component->id, 'gross_minor' => 10000, 'net_minor' => 10000, 'currency' => 'USD',
    ]);

    $requester = User::factory()->create();
    $approver = User::factory()->create();

    $waiver = app(RequestFeeWaiverAction::class)->execute(new RequestFeeWaiverData(
        schoolId: $f['school']->id, termId: $f['term']->id, studentId: $student->id, type: 'write_off',
        amountMinor: 4000, currency: 'USD', reasonCode: 'uncollectable', reason: 'Family emigrated, uncontactable.',
        requestedByUserId: $requester->id, invoiceId: $invoice->id,
    ));

    expect($waiver->status)->toBe('pending');

    expect(fn () => app(ApproveFeeWaiverAction::class)->execute(new ApproveFeeWaiverData(
        feeWaiverId: $waiver->id, approvedByUserId: $requester->id, contraAccountId: $badDebt->id, debtorAccountId: $debtor->id,
    )))->toThrow(InvalidStateTransitionException::class);

    $posted = app(ApproveFeeWaiverAction::class)->execute(new ApproveFeeWaiverData(
        feeWaiverId: $waiver->id, approvedByUserId: $approver->id, contraAccountId: $badDebt->id, debtorAccountId: $debtor->id,
    ));

    expect($posted->status)->toBe('posted')
        ->and($posted->journal_id)->not->toBeNull()
        ->and($invoice->fresh()->balance_minor)->toBe(6000)
        ->and($invoice->fresh()->written_off_minor)->toBe(4000);
});

it('rejects a pending waiver, leaving the invoice untouched', function (): void {
    $f = fin03Fixture();
    $student = fin03Student($f);

    $waiver = app(RequestFeeWaiverAction::class)->execute(new RequestFeeWaiverData(
        schoolId: $f['school']->id, termId: $f['term']->id, studentId: $student->id, type: 'waiver',
        amountMinor: 1000, currency: 'USD', reasonCode: 'goodwill', reason: 'Long-standing family, goodwill gesture.',
        requestedByUserId: $f['user']->id,
    ));

    $rejected = app(RejectFeeWaiverAction::class)->execute(new RejectFeeWaiverData($waiver->id, User::factory()->create()->id));

    expect($rejected->status)->toBe('rejected')
        ->and(FeeWaiver::where('id', $waiver->id)->first()->journal_id)->toBeNull();
});

it('creates a payment plan splitting the total evenly across instalments, approves it, and completes it once every instalment is paid', function (): void {
    $f = fin03Fixture();
    $student = fin03Student($f);
    $guardian = Guardian::factory()->for($f['school'])->create();

    $plan = app(CreatePaymentPlanAction::class)->execute(new CreatePaymentPlanData(
        schoolId: $f['school']->id, studentId: $student->id, partyType: 'guardian', partyId: $guardian->id,
        totalMinor: 10000, currency: 'USD', instalmentCount: 3, firstDueDate: now()->addMonth(),
        createdByUserId: $f['user']->id,
    ));

    expect($plan->instalments)->toHaveCount(3)
        ->and($plan->instalments->sum('amount_minor'))->toBe(10000)
        ->and($plan->status)->toBe('proposed');

    $active = app(ApprovePaymentPlanAction::class)->execute(new ApprovePaymentPlanData($plan->id, User::factory()->create()->id));
    expect($active->status)->toBe('active');

    foreach ($plan->instalments as $instalment) {
        app(RecordPaymentPlanInstalmentPaymentAction::class)->execute(new RecordPaymentPlanInstalmentPaymentData($instalment->id, $instalment->amount_minor));
    }

    expect($plan->fresh()->status)->toBe('completed');
});

it('cancels a payment plan and refuses to cancel one already completed', function (): void {
    $f = fin03Fixture();
    $student = fin03Student($f);
    $guardian = Guardian::factory()->for($f['school'])->create();

    $plan = app(CreatePaymentPlanAction::class)->execute(new CreatePaymentPlanData(
        schoolId: $f['school']->id, studentId: $student->id, partyType: 'guardian', partyId: $guardian->id,
        totalMinor: 5000, currency: 'USD', instalmentCount: 1, firstDueDate: now()->addMonth(),
        createdByUserId: $f['user']->id,
    ));

    $cancelled = app(CancelPaymentPlanAction::class)->execute(new CancelPaymentPlanData($plan->id));
    expect($cancelled->status)->toBe('cancelled');

    expect(fn () => app(CancelPaymentPlanAction::class)->execute(new CancelPaymentPlanData($plan->id)))
        ->toThrow(InvalidStateTransitionException::class);
});

it('marks an active payment plan breached once an instalment is overdue past the grace period (BR-FIN-03-017)', function (): void {
    $f = fin03Fixture();
    $student = fin03Student($f);
    $guardian = Guardian::factory()->for($f['school'])->create();

    $plan = app(CreatePaymentPlanAction::class)->execute(new CreatePaymentPlanData(
        schoolId: $f['school']->id, studentId: $student->id, partyType: 'guardian', partyId: $guardian->id,
        totalMinor: 5000, currency: 'USD', instalmentCount: 1, firstDueDate: now()->subDays(30),
        createdByUserId: $f['user']->id,
    ));
    app(ApprovePaymentPlanAction::class)->execute(new ApprovePaymentPlanData($plan->id, User::factory()->create()->id));

    $breachedCount = app(CheckPaymentPlanBreachesAction::class)->execute();

    expect($breachedCount)->toBeGreaterThanOrEqual(1)
        ->and($plan->fresh()->status)->toBe('breached')
        ->and($plan->fresh()->breach_count)->toBe(1);
});

it('records a debtor chase note, append-only', function (): void {
    $f = fin03Fixture();
    $student = fin03Student($f);

    $note = app(RecordDebtorChaseNoteAction::class)->execute(new RecordDebtorChaseNoteData(
        schoolId: $f['school']->id, studentId: $student->id, outcome: 'promised_to_pay',
        note: 'Spoke to father, promised payment by Friday.', recordedByUserId: $f['user']->id,
    ));

    expect($note->outcome)->toBe('promised_to_pay');

    expect(fn () => $note->update(['note' => 'edited']))->toThrow(InvalidStateTransitionException::class);
});

it('withholds a report card when the gated balance exceeds the threshold, and honours an override (BR-FIN-03-018/AC-FIN-03-007)', function (): void {
    $f = fin03Fixture();
    $student = fin03Student($f);
    $income = Account::factory()->for($f['school'])->income()->create();
    $debtor = Account::factory()->for($f['school'])->controlAccount('student')->create();
    $component = FeeComponent::factory()->for($f['school'])->create([
        'income_account_id' => $income->id, 'debtor_account_id' => $debtor->id, 'counts_toward_report_gate' => true,
    ]);

    $invoice = Invoice::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'student_id' => $student->id,
        'gross_minor' => 34000, 'net_minor' => 34000, 'balance_minor' => 34000, 'currency' => 'USD',
    ]);
    InvoiceLine::factory()->for($f['school'])->create([
        'invoice_id' => $invoice->id, 'component_id' => $component->id, 'gross_minor' => 34000, 'net_minor' => 34000, 'currency' => 'USD',
    ]);

    app(SetSettingValueAction::class)->execute(new SetSettingValueData('finance.report_gate_enabled', SettingScope::School, $f['school']->id, true));
    app(SetSettingValueAction::class)->execute(new SetSettingValueData('finance.report_gate_threshold_minor', SettingScope::School, $f['school']->id, 10000));

    $result = app(CheckReportGateAction::class)->execute(new CheckReportGateData($f['school']->id, $student->id, $f['term']->id));
    expect($result->isWithheld)->toBeTrue()
        ->and($result->gateBalanceMinor)->toBe(34000);

    app(GrantReportGateOverrideAction::class)->execute(new GrantReportGateOverrideData(
        schoolId: $f['school']->id, studentId: $student->id, termId: $f['term']->id,
        reason: 'Hardship case approved by the bursar.', grantedByUserId: $f['user']->id,
    ));

    $overridden = app(CheckReportGateAction::class)->execute(new CheckReportGateData($f['school']->id, $student->id, $f['term']->id));
    expect($overridden->isWithheld)->toBeFalse();
});

it('generates a statement from journal lines with a correct opening and running balance (BR-FIN-03-009)', function (): void {
    $f = fin03Fixture();
    $student = fin03Student($f);
    $cash = Account::factory()->for($f['school'])->create(['code' => '1110', 'currency' => 'USD']);
    $debtor = Account::factory()->for($f['school'])->controlAccount('student')->create();
    $income = Account::factory()->for($f['school'])->income()->create();

    // Before the statement window: an opening charge.
    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'FEE_BILLING', narration: 'Opening charge',
        lines: [
            new JournalLineData($debtor->id, 'DR', Money::of(10000, Currency::USD), subledgerType: 'student', subledgerId: $student->id),
            new JournalLineData($income->id, 'CR', Money::of(10000, Currency::USD)),
        ],
        effectiveAt: now()->subDays(20), postedByUserId: $f['user']->id,
    ));

    // Inside the statement window: a payment.
    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'RECEIPT', narration: 'Payment received',
        lines: [
            new JournalLineData($cash->id, 'DR', Money::of(4000, Currency::USD)),
            new JournalLineData($debtor->id, 'CR', Money::of(4000, Currency::USD), subledgerType: 'student', subledgerId: $student->id),
        ],
        effectiveAt: now()->subDays(5), postedByUserId: $f['user']->id,
    ));

    $statement = app(GenerateStatementAction::class)->execute(new GenerateStatementData(
        schoolId: $f['school']->id, subledgerType: 'student', subledgerId: $student->id, currency: 'USD',
        from: now()->subDays(10), to: now(),
    ));

    expect($statement->openingBalanceMinor)->toBe(10000)
        ->and($statement->lines)->toHaveCount(1)
        ->and($statement->lines[0]->direction)->toBe('CR')
        ->and($statement->closingBalanceMinor)->toBe(6000);
});

it('buckets an overdue invoice into the correct aging bucket (BR-FIN-03-014)', function (): void {
    $f = fin03Fixture();
    $student = fin03Student($f);

    Invoice::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'student_id' => $student->id,
        'due_date' => now()->subDays(45), 'gross_minor' => 5000, 'net_minor' => 5000, 'balance_minor' => 5000, 'currency' => 'USD',
    ]);

    $rows = app(GenerateAgedDebtorsReportAction::class)->execute(new GenerateAgedDebtorsReportData(
        schoolId: $f['school']->id, currency: 'USD', asAt: now(),
    ));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->bucketMinor['31-60'])->toBe(5000)
        ->and($rows[0]->totalMinor)->toBe(5000);
});
