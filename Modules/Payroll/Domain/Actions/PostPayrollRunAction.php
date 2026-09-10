<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Payroll\Domain\DataObjects\PostPayrollRunData;
use Modules\Payroll\Domain\DataObjects\PrepareStatutoryReturnsData;
use Modules\Payroll\Domain\Events\PayrollPosted;
use Modules\Payroll\Domain\Support\LoanRepaymentApplier;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\StaffLoan;

/**
 * ACT-PostPayrollRun (Book H3 PPL-05 §4 ⭐, the GL posting layout).
 * One journal for the whole run, following the spec's Dr/Cr table
 * exactly, except "Salaries & Wages (by cost centre)" posts as one
 * undivided debit line — see `PayrollGlAccounts`'s docblock. This is
 * also where a loan's `outstanding_minor`/`paid_minor` actually move
 * — never at compute/preview time, since a run that never posts must
 * never have touched a real balance.
 */
final class PostPayrollRunAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
        private readonly LoanRepaymentApplier $loanRepayments,
        private readonly PrepareStatutoryReturnsAction $prepareReturns,
    ) {}

    public function execute(PostPayrollRunData $data): PayrollRun
    {
        $run = PayrollRun::with('payslips')->findOrFail($data->payrollRunId);

        if ($run->status !== 'approved') {
            throw new InvalidStateTransitionException(
                "A payroll run can only be posted from [approved]; this one is [{$run->status}].",
                ['status' => $run->status],
            );
        }

        // Assumes every payslip on the run shares one currency — true
        // for as long as split-currency salaries (BR-PPL-05-010) stay
        // deferred (see `ComputePayslipAction`'s own docblock). A run
        // with zero payslips has nothing to post at all.
        $firstPayslip = $run->payslips->first();

        if ($firstPayslip === null) {
            throw new InvalidStateTransitionException('A payroll run with no payslips cannot be posted.', ['payroll_run_id' => $run->id]);
        }

        $currency = Currency::from($firstPayslip->currency);
        $accounts = $data->glAccounts;

        $lines = [];
        $money = fn (int $minor): Money => Money::of($minor, $currency);

        $lines[] = new JournalLineData($accounts->salariesExpenseAccountId, 'DR', $money($run->gross_minor), narration: 'Salaries & wages');
        $lines[] = new JournalLineData($accounts->employerNssaExpenseAccountId, 'DR', $money($run->nssa_employer_minor), narration: 'Employer NSSA');
        $lines[] = new JournalLineData($accounts->employerApwcsExpenseAccountId, 'DR', $money($run->apwcs_minor), narration: 'Employer APWCS');
        $lines[] = new JournalLineData($accounts->zimdefExpenseAccountId, 'DR', $money($run->zimdef_minor), narration: 'ZIMDEF levy');
        $lines[] = new JournalLineData($accounts->employerNecExpenseAccountId, 'DR', $money($run->nec_employer_minor), narration: 'Employer NEC');

        $lines[] = new JournalLineData($accounts->netSalariesPayableAccountId, 'CR', $money($run->net_minor), narration: 'Net salaries payable');
        $lines[] = new JournalLineData($accounts->payePayableAccountId, 'CR', $money($run->paye_minor), narration: 'PAYE payable');
        $lines[] = new JournalLineData($accounts->aidsLevyPayableAccountId, 'CR', $money($run->aids_levy_minor), narration: 'AIDS Levy payable');
        $lines[] = new JournalLineData($accounts->nssaPayableAccountId, 'CR', $money($run->nssa_employee_minor + $run->nssa_employer_minor), narration: 'NSSA payable');
        $lines[] = new JournalLineData($accounts->apwcsPayableAccountId, 'CR', $money($run->apwcs_minor), narration: 'APWCS payable');
        $lines[] = new JournalLineData($accounts->zimdefPayableAccountId, 'CR', $money($run->zimdef_minor), narration: 'ZIMDEF payable');
        $lines[] = new JournalLineData($accounts->necPayableAccountId, 'CR', $money($run->nec_employee_minor + $run->nec_employer_minor), narration: 'NEC payable');

        $loanDeductionTotal = (int) $run->payslips->sum('loan_deduction_minor');
        $thirdPartyTotal = (int) $run->payslips->sum('third_party_minor');

        if ($loanDeductionTotal > 0) {
            $lines[] = new JournalLineData($accounts->staffLoansReceivableAccountId, 'CR', $money($loanDeductionTotal), narration: 'Staff loan repayments');
        }

        if ($thirdPartyTotal > 0) {
            $lines[] = new JournalLineData($accounts->thirdPartyPayablesAccountId, 'CR', $money($thirdPartyTotal), narration: 'Third-party deductions');
        }

        foreach ($this->feeOffsetLines($run, $accounts->feeDebtorsAccountId, $currency) as $line) {
            $lines[] = $line;
        }

        $lines = array_values(array_filter($lines, fn (JournalLineData $line): bool => ! $line->amount->isZero()));

        return $this->transaction(function () use ($run, $data, $lines): PayrollRun {
            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $run->school_id,
                academicYearId: $run->academic_year_id,
                termId: $run->term_id,
                journalType: 'PAYROLL',
                narration: "Payroll run {$run->run_number} — {$run->period_month}",
                lines: $lines,
                effectiveAt: Carbon::parse($run->pay_date),
                postedByUserId: $data->postedByUserId,
                sourceType: 'payroll_run',
                sourceId: $run->id,
            ));

            $this->loanRepayments->apply($run);

            $run->update(['status' => 'posted', 'journal_id' => $journal->id]);

            event(new PayrollPosted($run));

            $this->prepareReturns->execute(new PrepareStatutoryReturnsData($run->id));

            return $run->fresh();
        });
    }

    /**
     * @return array<int, JournalLineData>
     */
    private function feeOffsetLines(PayrollRun $run, int $feeDebtorsAccountId, Currency $currency): array
    {
        $staffIdsWithOffset = $run->payslips->where('fee_offset_minor', '>', 0)->pluck('staff_id');

        if ($staffIdsWithOffset->isEmpty()) {
            return [];
        }

        $perStudent = [];

        $loans = StaffLoan::whereIn('staff_id', $staffIdsWithOffset)
            ->where('loan_type', 'fee_offset')
            ->where('status', 'active')
            ->get();

        foreach ($loans as $loan) {
            $instalment = min($loan->instalment_minor, $loan->outstanding_minor);
            $studentIds = $loan->offset_student_ids ?? [];

            if ($instalment <= 0 || $studentIds === []) {
                continue;
            }

            $shares = Money::of($instalment, $currency)->allocate(array_fill(0, count($studentIds), 1));

            foreach (array_values($studentIds) as $index => $studentId) {
                $perStudent[$studentId] = ($perStudent[$studentId] ?? 0) + $shares[$index]->minor;
            }
        }

        return array_map(
            fn (int $studentId, int $amountMinor): JournalLineData => new JournalLineData(
                accountId: $feeDebtorsAccountId,
                direction: 'CR',
                amount: Money::of($amountMinor, $currency),
                subledgerType: 'student',
                subledgerId: $studentId,
                narration: 'Staff-child fee offset',
            ),
            array_keys($perStudent),
            array_values($perStudent),
        );
    }
}
