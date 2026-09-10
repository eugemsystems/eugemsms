<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Journal;
use Modules\Payroll\Domain\Actions\AddStaffPayComponentAction;
use Modules\Payroll\Domain\Actions\ApprovePayrollRunAction;
use Modules\Payroll\Domain\Actions\CheckStatutoryReturnDeadlinesAction;
use Modules\Payroll\Domain\Actions\ComputePayrollRunAction;
use Modules\Payroll\Domain\Actions\ComputePayslipAction;
use Modules\Payroll\Domain\Actions\CreatePayComponentAction;
use Modules\Payroll\Domain\Actions\CreateStaffLoanAction;
use Modules\Payroll\Domain\Actions\CreateStaffPayStructureAction;
use Modules\Payroll\Domain\Actions\CreateStatutoryConfigurationAction;
use Modules\Payroll\Domain\Actions\PostPayrollRunAction;
use Modules\Payroll\Domain\Actions\PrepareItf16ReturnAction;
use Modules\Payroll\Domain\Actions\RecordPayrollPaymentAction;
use Modules\Payroll\Domain\Actions\RecordStatutoryReturnSubmissionAction;
use Modules\Payroll\Domain\DataObjects\AddStaffPayComponentData;
use Modules\Payroll\Domain\DataObjects\ApprovePayrollRunData;
use Modules\Payroll\Domain\DataObjects\ComputePayrollRunData;
use Modules\Payroll\Domain\DataObjects\ComputePayslipData;
use Modules\Payroll\Domain\DataObjects\CreatePayComponentData;
use Modules\Payroll\Domain\DataObjects\CreateStaffLoanData;
use Modules\Payroll\Domain\DataObjects\CreateStaffPayStructureData;
use Modules\Payroll\Domain\DataObjects\CreateStatutoryConfigurationData;
use Modules\Payroll\Domain\DataObjects\PayrollGlAccounts;
use Modules\Payroll\Domain\DataObjects\PostPayrollRunData;
use Modules\Payroll\Domain\DataObjects\PrepareItf16ReturnData;
use Modules\Payroll\Domain\DataObjects\RecordPayrollPaymentData;
use Modules\Payroll\Domain\DataObjects\RecordStatutoryReturnSubmissionData;
use Modules\Payroll\Domain\Events\StatutoryReturnDue;
use Modules\Payroll\Domain\Events\StatutoryReturnOverdue;
use Modules\Payroll\Domain\Exceptions\Itf16ReconciliationException;
use Modules\Payroll\Domain\Exceptions\PayrollComputationException;
use Modules\Payroll\Domain\Exceptions\UnconfirmedStatutoryConfigException;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\StatutoryReturn;
use Modules\People\Domain\Actions\CreateStaffAction;
use Modules\People\Domain\DataObjects\CreateStaffData;
use Modules\People\Models\LeaveRequest;
use Modules\People\Models\LeaveType;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffContract;

/**
 * @return array<string, mixed>
 */
function ppl05Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    $user = User::factory()->create();

    foreach (['staff' => 'STF', 'payroll_run' => 'RUN', 'payslip' => 'PSL', 'journal' => 'JNL'] as $documentType => $prefix) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $documentType, pattern: $prefix.'/{SEQ:6}',
        ));
    }

    app(CreateStatutoryConfigurationAction::class)->execute(new CreateStatutoryConfigurationData(
        configType: 'nssa_pension',
        configuration: ['employee_rate' => '0.045', 'employer_rate' => '0.045', 'ceiling_minor' => 70000],
        effectiveFrom: now()->subYear(),
        createdByUserId: $user->id,
        currency: 'USD',
    ));

    app(CreateStatutoryConfigurationAction::class)->execute(new CreateStatutoryConfigurationData(
        configType: 'paye_bands',
        configuration: ['bands' => [['from_minor' => 0, 'to_minor' => null, 'rate' => '0.24']]],
        effectiveFrom: now()->subYear(),
        createdByUserId: $user->id,
        currency: 'USD',
    ));

    app(CreateStatutoryConfigurationAction::class)->execute(new CreateStatutoryConfigurationData(
        configType: 'aids_levy',
        configuration: ['rate' => '0.03'],
        effectiveFrom: now()->subYear(),
        createdByUserId: $user->id,
    ));

    app(CreateStatutoryConfigurationAction::class)->execute(new CreateStatutoryConfigurationData(
        configType: 'zimdef',
        configuration: ['rate' => '0.01'],
        effectiveFrom: now()->subYear(),
        createdByUserId: $user->id,
    ));

    $basicComponent = app(CreatePayComponentAction::class)->execute(new CreatePayComponentData(
        schoolId: $school->id, code: 'BASIC', name: 'Basic Salary', componentType: 'earning',
        category: 'basic', calculationMethod: 'fixed',
    ));

    $glAccounts = new PayrollGlAccounts(
        salariesExpenseAccountId: Account::factory()->for($school)->expense()->create()->id,
        employerNssaExpenseAccountId: Account::factory()->for($school)->expense()->create()->id,
        employerApwcsExpenseAccountId: Account::factory()->for($school)->expense()->create()->id,
        zimdefExpenseAccountId: Account::factory()->for($school)->expense()->create()->id,
        employerNecExpenseAccountId: Account::factory()->for($school)->expense()->create()->id,
        netSalariesPayableAccountId: Account::factory()->for($school)->liability()->create()->id,
        payePayableAccountId: Account::factory()->for($school)->liability()->create()->id,
        aidsLevyPayableAccountId: Account::factory()->for($school)->liability()->create()->id,
        nssaPayableAccountId: Account::factory()->for($school)->liability()->create()->id,
        apwcsPayableAccountId: Account::factory()->for($school)->liability()->create()->id,
        zimdefPayableAccountId: Account::factory()->for($school)->liability()->create()->id,
        necPayableAccountId: Account::factory()->for($school)->liability()->create()->id,
        staffLoansReceivableAccountId: Account::factory()->for($school)->create()->id,
        feeDebtorsAccountId: Account::factory()->for($school)->controlAccount('student')->create()->id,
        thirdPartyPayablesAccountId: Account::factory()->for($school)->liability()->create()->id,
    );

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'user' => $user,
        'basicComponent' => $basicComponent, 'glAccounts' => $glAccounts,
    ];
}

/**
 * @param  array<string, mixed>  $f
 */
function createPayrollStaff(array $f, int $basicMinor = 103150): Staff
{
    $staff = app(CreateStaffAction::class)->execute(new CreateStaffData(
        schoolId: $f['school']->id, firstName: 'Tendai', lastName: 'Moyo', dateOfBirth: now()->subYears(30),
        gender: 'male', primaryPhone: '+263771234567', staffCategory: 'teaching',
        joinedOn: now()->subYears(2), createdByUserId: $f['user']->id, isTeaching: true,
    ));

    $staff->update(['bank_account_number' => '1234567890', 'nssa_number' => 'NSSA-001']);

    $structure = app(CreateStaffPayStructureAction::class)->execute(new CreateStaffPayStructureData(
        schoolId: $f['school']->id, staffId: $staff->id, primaryCurrency: 'USD', paymentCurrency: 'USD',
        effectiveFrom: now()->subYear(), approvedByUserId: $f['user']->id,
    ));

    app(AddStaffPayComponentAction::class)->execute(new AddStaffPayComponentData(
        schoolId: $f['school']->id, payStructureId: $structure->id, componentId: $f['basicComponent']->id,
        currency: 'USD', effectiveFrom: now()->subYear(), amountMinor: $basicMinor,
    ));

    return $staff->fresh();
}

it('blocks computation while a statutory configuration is unconfirmed (AC-PPL-05-001)', function (): void {
    $f = ppl05Fixture();
    $staff = createPayrollStaff($f);

    app(CreateStatutoryConfigurationAction::class)->execute(new CreateStatutoryConfigurationData(
        configType: 'nssa_pension',
        configuration: ['employee_rate' => '0.05', 'employer_rate' => '0.05', 'ceiling_minor' => 70000],
        effectiveFrom: now()->subMonth(),
        createdByUserId: $f['user']->id,
        currency: 'USD',
        requiresConfirmation: true,
    ));

    $run = PayrollRun::factory()->create(['school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'computed_by' => $f['user']->id]);

    expect(fn () => app(ComputePayslipAction::class)->execute(new ComputePayslipData(
        payrollRunId: $run->id, staffId: $staff->id, payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(),
    )))->toThrow(UnconfirmedStatutoryConfigException::class);
});

it('computes AIDS Levy as a percentage of PAYE due, never of gross (AC-PPL-05-003)', function (): void {
    $f = ppl05Fixture();
    $staff = createPayrollStaff($f, basicMinor: 103150);

    $run = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));

    $payslip = $run->payslips()->where('staff_id', $staff->id)->firstOrFail();

    expect((int) $payslip->paye_minor)->toBe(24000)
        ->and((int) $payslip->aids_levy_minor)->toBe(720);
});

it('caps NSSA pension at the insurable earnings ceiling, splitting 4.5/4.5 (AC-PPL-05-004)', function (): void {
    $f = ppl05Fixture();
    $staff = createPayrollStaff($f, basicMinor: 100000);

    $run = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));

    $payslip = $run->payslips()->where('staff_id', $staff->id)->firstOrFail();

    expect((int) $payslip->nssa_employee_minor)->toBe(3150)
        ->and((int) $payslip->nssa_employer_minor)->toBe(3150)
        ->and($payslip->calculation_trace[0]['ceiling_applied'])->toBeTrue();
});

it('computes APWCS on the full uncapped wage bill, employer-only, never on employee deductions (AC-PPL-05-004B)', function (): void {
    $f = ppl05Fixture();
    app(CreateStatutoryConfigurationAction::class)->execute(new CreateStatutoryConfigurationData(
        configType: 'nssa_apwcs', configuration: ['employer_rate' => '0.02'], effectiveFrom: now()->subYear(), createdByUserId: $f['user']->id,
    ));
    $staff = createPayrollStaff($f, basicMinor: 100000);

    $run = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));

    $payslip = $run->payslips()->where('staff_id', $staff->id)->firstOrFail();

    expect((int) $payslip->apwcs_minor)->toBe(2000)
        ->and((int) $payslip->employer_cost_minor)->toBeGreaterThan((int) $payslip->gross_minor);
});

it('excludes a negative-net-pay staff member from the run while completing everyone else (AC-PPL-05-006)', function (): void {
    $f = ppl05Fixture();
    $normal = createPayrollStaff($f, basicMinor: 100000);
    $overCommitted = createPayrollStaff($f, basicMinor: 10000);

    app(CreateStaffLoanAction::class)->execute(new CreateStaffLoanData(
        schoolId: $f['school']->id, staffId: $overCommitted->id, loanType: 'staff_loan', principalMinor: 50000,
        currency: 'USD', instalmentMinor: 50000, instalmentCount: 1, startsOn: now()->subMonth(),
    ));

    $run = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));

    expect($run->payslips()->where('staff_id', $normal->id)->exists())->toBeTrue()
        ->and($run->payslips()->where('staff_id', $overCommitted->id)->exists())->toBeFalse();

    $exceptionReasons = collect($run->exception_report)->pluck('reason');
    expect($exceptionReasons)->toContain('negative_net_pay');
});

it('refuses to post a run that has not been approved (AC-PPL-05-005/BR-PPL-05-013)', function (): void {
    $f = ppl05Fixture();
    createPayrollStaff($f, basicMinor: 100000);

    $run = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));

    expect(fn () => app(PostPayrollRunAction::class)->execute(new PostPayrollRunData($run->id, $f['glAccounts'], $f['user']->id)))
        ->toThrow(InvalidStateTransitionException::class);

    expect($run->fresh()->journal_id)->toBeNull();
});

it('posts one balanced journal for the whole run and prepares statutory returns due the 10th of next month (BR-PPL-05-020/AC-PPL-05-007)', function (): void {
    $f = ppl05Fixture();
    createPayrollStaff($f, basicMinor: 100000);

    $run = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));

    app(ApprovePayrollRunAction::class)->execute(new ApprovePayrollRunData($run->id, $f['user']->id));

    $posted = app(PostPayrollRunAction::class)->execute(new PostPayrollRunData($run->id, $f['glAccounts'], $f['user']->id));

    expect($posted->status)->toBe('posted')
        ->and($posted->journal_id)->not->toBeNull();

    $journal = Journal::with('lines')->find($posted->journal_id);
    $totalDebits = $journal->lines->where('direction', 'DR')->sum('amount_minor');
    $totalCredits = $journal->lines->where('direction', 'CR')->sum('amount_minor');
    expect((int) $totalDebits)->toBe((int) $totalCredits);

    $p2 = StatutoryReturn::where('school_id', $f['school']->id)->where('return_type', 'p2_paye')->firstOrFail();
    expect($p2->due_date->format('d'))->toBe('10')
        ->and($p2->due_date->format('Y-m'))->toBe(now()->addMonthNoOverflow()->format('Y-m'))
        ->and((int) $p2->amount_due_minor)->toBe((int) $run->fresh()->paye_minor);

    expect(fn () => app(PostPayrollRunAction::class)->execute(new PostPayrollRunData($run->id, $f['glAccounts'], $f['user']->id)))
        ->toThrow(InvalidStateTransitionException::class);
});

it('credits the named learner\'s fee account for a staff-child fee offset (AC-PPL-05-008)', function (): void {
    $f = ppl05Fixture();
    $staff = createPayrollStaff($f, basicMinor: 100000);

    app(CreateStaffLoanAction::class)->execute(new CreateStaffLoanData(
        schoolId: $f['school']->id, staffId: $staff->id, loanType: 'fee_offset', principalMinor: 50000,
        currency: 'USD', instalmentMinor: 10000, instalmentCount: 5, startsOn: now()->subMonth(),
        offsetStudentIds: [42],
    ));

    $run = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));

    $payslip = $run->payslips()->where('staff_id', $staff->id)->firstOrFail();
    expect((int) $payslip->fee_offset_minor)->toBe(10000);

    app(ApprovePayrollRunAction::class)->execute(new ApprovePayrollRunData($run->id, $f['user']->id));
    $posted = app(PostPayrollRunAction::class)->execute(new PostPayrollRunData($run->id, $f['glAccounts'], $f['user']->id));

    $journal = Journal::with('lines')->find($posted->journal_id);
    $feeLine = $journal->lines->firstWhere('subledger_id', 42);

    expect($feeLine)->not->toBeNull()
        ->and($feeLine->subledger_type)->toBe('student')
        ->and((int) $feeLine->amount_minor)->toBe(10000)
        ->and($feeLine->direction)->toBe('CR');
});

it('never over-recovers a loan and completes it once the balance is cleared (BR-PPL-05-019)', function (): void {
    $f = ppl05Fixture();
    $staff = createPayrollStaff($f, basicMinor: 100000);

    $loan = app(CreateStaffLoanAction::class)->execute(new CreateStaffLoanData(
        schoolId: $f['school']->id, staffId: $staff->id, loanType: 'staff_loan', principalMinor: 5000,
        currency: 'USD', instalmentMinor: 10000, instalmentCount: 1, startsOn: now()->subMonth(),
    ));

    $run = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));

    $payslip = $run->payslips()->where('staff_id', $staff->id)->firstOrFail();
    expect((int) $payslip->loan_deduction_minor)->toBe(5000);

    app(ApprovePayrollRunAction::class)->execute(new ApprovePayrollRunData($run->id, $f['user']->id));
    app(PostPayrollRunAction::class)->execute(new PostPayrollRunData($run->id, $f['glAccounts'], $f['user']->id));

    expect((int) $loan->fresh()->outstanding_minor)->toBe(0)
        ->and($loan->fresh()->status)->toBe('completed');
});

it('pro-rates gross for unpaid leave taken within the pay period (BR-PPL-05-011)', function (): void {
    $f = ppl05Fixture();
    $staff = createPayrollStaff($f, basicMinor: 300000);

    $leaveType = LeaveType::factory()->for($f['school'])->create(['is_paid' => false, 'code' => 'UNPAID']);
    LeaveRequest::factory()->create([
        'school_id' => $f['school']->id, 'staff_id' => $staff->id, 'leave_type_id' => $leaveType->id,
        'academic_year_id' => $f['year']->id, 'status' => 'approved',
        'starts_on' => now()->startOfMonth()->toDateString(), 'ends_on' => now()->startOfMonth()->addDays(9)->toDateString(),
        'working_days' => 10,
    ]);

    $run = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));

    $payslip = $run->payslips()->where('staff_id', $staff->id)->firstOrFail();

    expect((int) $payslip->gross_minor)->toBeLessThan(300000)
        ->and((float) $payslip->unpaid_leave_days)->toBe(10.0);
});

it('applies the USD PAYE band table to a USD earner and a different ZWG table to a ZWG earner in the same run (BR-PPL-05-003/AC-PPL-05-002)', function (): void {
    $f = ppl05Fixture();

    app(CreateStatutoryConfigurationAction::class)->execute(new CreateStatutoryConfigurationData(
        configType: 'nssa_pension', configuration: ['employee_rate' => '0.045', 'employer_rate' => '0.045', 'ceiling_minor' => 7000000],
        effectiveFrom: now()->subYear(), createdByUserId: $f['user']->id, currency: 'ZWG',
    ));
    app(CreateStatutoryConfigurationAction::class)->execute(new CreateStatutoryConfigurationData(
        configType: 'paye_bands', configuration: ['bands' => [['from_minor' => 0, 'to_minor' => null, 'rate' => '0.10']]],
        effectiveFrom: now()->subYear(), createdByUserId: $f['user']->id, currency: 'ZWG',
    ));

    $usdStaff = createPayrollStaff($f, basicMinor: 100000);

    $zwgStaff = app(CreateStaffAction::class)->execute(new CreateStaffData(
        schoolId: $f['school']->id, firstName: 'Rudo', lastName: 'Chikwava', dateOfBirth: now()->subYears(28),
        gender: 'female', primaryPhone: '+263771234568', staffCategory: 'teaching',
        joinedOn: now()->subYears(1), createdByUserId: $f['user']->id, isTeaching: true,
    ));
    $zwgStaff->update(['bank_account_number' => '9876543210', 'nssa_number' => 'NSSA-002']);
    $zwgStructure = app(CreateStaffPayStructureAction::class)->execute(new CreateStaffPayStructureData(
        schoolId: $f['school']->id, staffId: $zwgStaff->id, primaryCurrency: 'ZWG', paymentCurrency: 'ZWG',
        effectiveFrom: now()->subYear(), approvedByUserId: $f['user']->id,
    ));
    app(AddStaffPayComponentAction::class)->execute(new AddStaffPayComponentData(
        schoolId: $f['school']->id, payStructureId: $zwgStructure->id, componentId: $f['basicComponent']->id,
        currency: 'ZWG', effectiveFrom: now()->subYear(), amountMinor: 100000,
    ));

    $run = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));

    $usdPayslip = $run->payslips()->where('staff_id', $usdStaff->id)->firstOrFail();
    $zwgPayslip = $run->payslips()->where('staff_id', $zwgStaff->id)->firstOrFail();

    expect($usdPayslip->currency)->toBe('USD')
        ->and($zwgPayslip->currency)->toBe('ZWG')
        ->and((int) $usdPayslip->paye_minor)->not->toBe((int) $zwgPayslip->paye_minor);
});

it('recomputes a historical pay date identically after the statutory configuration is superseded (BR-PPL-05-004/AC-PPL-05-009)', function (): void {
    $f = ppl05Fixture();
    $staff = createPayrollStaff($f, basicMinor: 100000);

    $firstRun = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));
    $originalNssa = (int) $firstRun->payslips()->where('staff_id', $staff->id)->firstOrFail()->nssa_employee_minor;

    app(CreateStatutoryConfigurationAction::class)->execute(new CreateStatutoryConfigurationData(
        configType: 'nssa_pension', configuration: ['employee_rate' => '0.10', 'employer_rate' => '0.10', 'ceiling_minor' => 70000],
        effectiveFrom: now()->addMonth(), createdByUserId: $f['user']->id, currency: 'USD',
    ));

    $secondRun = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));
    $recomputedNssa = (int) $secondRun->payslips()->where('staff_id', $staff->id)->firstOrFail()->nssa_employee_minor;

    expect($recomputedNssa)->toBe($originalNssa);
});

it('splits NEC dues between employee and employer per the configured CBA (BR-PPL-05-009)', function (): void {
    $f = ppl05Fixture();
    app(CreateStatutoryConfigurationAction::class)->execute(new CreateStatutoryConfigurationData(
        configType: 'nec_dues', configuration: ['employee_rate' => '0.01', 'employer_rate' => '0.02'],
        effectiveFrom: now()->subYear(), createdByUserId: $f['user']->id,
    ));
    $staff = createPayrollStaff($f, basicMinor: 100000);

    $run = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));

    $payslip = $run->payslips()->where('staff_id', $staff->id)->firstOrFail();

    expect((int) $payslip->nec_employee_minor)->toBe(1000)
        ->and((int) $payslip->nec_employer_minor)->toBe(2000);
});

it('raises exceptions for a staff member with no bank details, no statutory identifiers, or an expired contract (BR-PPL-05-015)', function (): void {
    $f = ppl05Fixture();

    $noBankStaff = app(CreateStaffAction::class)->execute(new CreateStaffData(
        schoolId: $f['school']->id, firstName: 'No', lastName: 'Bank', dateOfBirth: now()->subYears(30),
        gender: 'male', primaryPhone: '+263771234569', staffCategory: 'teaching',
        joinedOn: now()->subYear(), createdByUserId: $f['user']->id, isTeaching: true,
    ));
    $structure = app(CreateStaffPayStructureAction::class)->execute(new CreateStaffPayStructureData(
        schoolId: $f['school']->id, staffId: $noBankStaff->id, primaryCurrency: 'USD', paymentCurrency: 'USD',
        effectiveFrom: now()->subYear(), approvedByUserId: $f['user']->id,
    ));
    app(AddStaffPayComponentAction::class)->execute(new AddStaffPayComponentData(
        schoolId: $f['school']->id, payStructureId: $structure->id, componentId: $f['basicComponent']->id,
        currency: 'USD', effectiveFrom: now()->subYear(), amountMinor: 100000,
    ));

    expect(fn () => app(ComputePayslipAction::class)->execute(new ComputePayslipData(
        payrollRunId: PayrollRun::factory()->create(['school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'computed_by' => $f['user']->id])->id,
        staffId: $noBankStaff->id, payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(),
    )))->toThrow(fn (PayrollComputationException $e) => $e->reason === 'missing_bank_details');

    $expiredContractStaff = createPayrollStaff($f, basicMinor: 100000);
    $contract = StaffContract::factory()->create([
        'school_id' => $f['school']->id, 'staff_id' => $expiredContractStaff->id, 'ends_on' => now()->subDay(),
    ]);
    $expiredStructure = app(CreateStaffPayStructureAction::class)->execute(new CreateStaffPayStructureData(
        schoolId: $f['school']->id, staffId: $expiredContractStaff->id, primaryCurrency: 'USD', paymentCurrency: 'USD',
        effectiveFrom: now()->subYear(), approvedByUserId: $f['user']->id, contractId: $contract->id,
    ));
    app(AddStaffPayComponentAction::class)->execute(new AddStaffPayComponentData(
        schoolId: $f['school']->id, payStructureId: $expiredStructure->id, componentId: $f['basicComponent']->id,
        currency: 'USD', effectiveFrom: now()->subYear(), amountMinor: 100000,
    ));

    expect(fn () => app(ComputePayslipAction::class)->execute(new ComputePayslipData(
        payrollRunId: PayrollRun::factory()->create(['school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'computed_by' => $f['user']->id])->id,
        staffId: $expiredContractStaff->id, payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(),
    )))->toThrow(fn (PayrollComputationException $e) => $e->reason === 'contract_expired');
});

it('refuses to edit a posted payroll run\'s figures (BR-PPL-05-016)', function (): void {
    $f = ppl05Fixture();
    createPayrollStaff($f, basicMinor: 100000);

    $run = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));
    app(ApprovePayrollRunAction::class)->execute(new ApprovePayrollRunData($run->id, $f['user']->id));
    $posted = app(PostPayrollRunAction::class)->execute(new PostPayrollRunData($run->id, $f['glAccounts'], $f['user']->id));

    expect(fn () => $posted->update(['gross_minor' => 1]))->toThrow(InvalidStateTransitionException::class);
});

it('marks a posted run paid once a bank file is recorded (§4 step 7)', function (): void {
    $f = ppl05Fixture();
    createPayrollStaff($f, basicMinor: 100000);

    $run = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));
    app(ApprovePayrollRunAction::class)->execute(new ApprovePayrollRunData($run->id, $f['user']->id));
    $posted = app(PostPayrollRunAction::class)->execute(new PostPayrollRunData($run->id, $f['glAccounts'], $f['user']->id));

    $bankFile = app(UploadFileAction::class)->execute(new UploadFileData(
        schoolId: $f['school']->id, category: 'payroll_bank_file', contents: "account,amount\n1234567890,1000\n",
        originalName: 'bank-file.csv', uploadedByUserId: $f['user']->id,
    ));

    $paid = app(RecordPayrollPaymentAction::class)->execute(new RecordPayrollPaymentData($run->id, $bankFile->id));

    expect($paid->status)->toBe('paid')
        ->and($paid->bank_file_id)->toBe($bankFile->id);
});

it('records manual submission of a statutory return with its reference (BR-PPL-05-022)', function (): void {
    $f = ppl05Fixture();
    createPayrollStaff($f, basicMinor: 100000);

    $run = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));
    app(ApprovePayrollRunAction::class)->execute(new ApprovePayrollRunData($run->id, $f['user']->id));
    app(PostPayrollRunAction::class)->execute(new PostPayrollRunData($run->id, $f['glAccounts'], $f['user']->id));

    $p2 = StatutoryReturn::where('school_id', $f['school']->id)->where('return_type', 'p2_paye')->firstOrFail();

    $submitted = app(RecordStatutoryReturnSubmissionAction::class)->execute(new RecordStatutoryReturnSubmissionData(
        statutoryReturnId: $p2->id, submissionReference: 'ZIMRA-REF-001',
    ));

    expect($submitted->status)->toBe('submitted')
        ->and($submitted->submission_reference)->toBe('ZIMRA-REF-001')
        ->and($submitted->submitted_at)->not->toBeNull();
});

it('fires due and overdue alerts for unresolved statutory returns (BR-PPL-05-021)', function (): void {
    Event::fake([StatutoryReturnDue::class, StatutoryReturnOverdue::class]);
    $f = ppl05Fixture();

    $dueSoon = StatutoryReturn::factory()->create([
        'school_id' => $f['school']->id, 'return_type' => 'p2_paye', 'period_reference' => '2026-01',
        'due_date' => now()->addDays(3)->toDateString(), 'status' => 'prepared',
    ]);
    $overdue = StatutoryReturn::factory()->create([
        'school_id' => $f['school']->id, 'return_type' => 'nssa_monthly', 'period_reference' => '2025-12',
        'due_date' => now()->subDays(2)->toDateString(), 'status' => 'pending',
    ]);

    $result = app(CheckStatutoryReturnDeadlinesAction::class)->execute($f['school']->id);

    expect($result['due']->pluck('id'))->toContain($dueSoon->id)
        ->and($result['overdue']->pluck('id'))->toContain($overdue->id);
    Event::assertDispatched(StatutoryReturnDue::class);
    Event::assertDispatched(StatutoryReturnOverdue::class);
});

it('reconciles ITF16 to the twelve monthly P2 returns and blocks preparation on a mismatch (BR-PPL-05-023/AC-PPL-05-011)', function (): void {
    $f = ppl05Fixture();
    $staff = createPayrollStaff($f, basicMinor: 100000);
    $taxYear = (int) now()->year;

    $run = app(ComputePayrollRunAction::class)->execute(new ComputePayrollRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        payDate: now(), periodStart: now()->startOfMonth(), periodEnd: now()->endOfMonth(), computedByUserId: $f['user']->id,
    ));
    app(ApprovePayrollRunAction::class)->execute(new ApprovePayrollRunData($run->id, $f['user']->id));
    app(PostPayrollRunAction::class)->execute(new PostPayrollRunData($run->id, $f['glAccounts'], $f['user']->id));

    $actualPaye = (int) $run->fresh()->paye_minor;

    // Only one real monthly P2 exists — fabricate the other eleven months
    // reconciling to the same total the payslips actually produced, so the
    // mismatch branch below is the only one that genuinely diverges.
    for ($month = 1; $month <= 12; $month++) {
        $reference = sprintf('%d-%02d', $taxYear, $month);

        StatutoryReturn::updateOrCreate(
            ['school_id' => $f['school']->id, 'return_type' => 'p2_paye', 'period_reference' => $reference],
            ['period_type' => 'monthly', 'due_date' => now()->toDateString(), 'amount_due_minor' => $reference === $run->fresh()->period_month ? $actualPaye : 0, 'currency' => 'USD', 'supporting_data' => [], 'status' => 'pending'],
        );
    }

    $itf16 = app(PrepareItf16ReturnAction::class)->execute(new PrepareItf16ReturnData($f['school']->id, $taxYear));

    expect($itf16->return_type)->toBe('itf16_annual')
        ->and((int) $itf16->amount_due_minor)->toBe($actualPaye);

    StatutoryReturn::where('school_id', $f['school']->id)
        ->where('return_type', 'p2_paye')
        ->where('period_reference', sprintf('%d-01', $taxYear))
        ->update(['amount_due_minor' => $actualPaye + 999999]);

    expect(fn () => app(PrepareItf16ReturnAction::class)->execute(new PrepareItf16ReturnData($f['school']->id, $taxYear)))
        ->toThrow(Itf16ReconciliationException::class);
});
