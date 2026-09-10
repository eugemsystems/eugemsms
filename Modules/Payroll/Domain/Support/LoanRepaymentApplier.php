<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Support;

use Modules\Payroll\Domain\Events\LoanFullyRecovered;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\StaffLoan;

/**
 * Called only from `PostPayrollRunAction`, inside its own
 * transaction — a loan balance only ever moves once its instalment
 * has actually been posted to the GL (BR-PPL-05-019). Re-derives
 * each loan's own instalment with the identical
 * `min(instalment_minor, outstanding_minor)` rule
 * `ComputePayslipAction` already used to build the payslip totals,
 * since a payslip's aggregate `loan_deduction_minor`/
 * `fee_offset_minor` doesn't retain which specific loan(s) it came
 * from when a staff member holds more than one. Not an `Action`
 * itself — it's an internal collaborator with no DTO of its own,
 * always invoked from inside another Action's transaction.
 */
final class LoanRepaymentApplier
{
    public function apply(PayrollRun $run): void
    {
        $staffIds = $run->payslips->pluck('staff_id');

        $loans = StaffLoan::whereIn('staff_id', $staffIds)->where('status', 'active')->get();

        foreach ($loans as $loan) {
            $instalment = min($loan->instalment_minor, $loan->outstanding_minor);

            if ($instalment <= 0) {
                continue;
            }

            $outstanding = $loan->outstanding_minor - $instalment;

            $loan->update([
                'outstanding_minor' => $outstanding,
                'paid_minor' => $loan->paid_minor + $instalment,
                'status' => $outstanding <= 0 ? 'completed' : 'active',
            ]);

            if ($outstanding <= 0) {
                event(new LoanFullyRecovered($loan->fresh()));
            }
        }
    }
}
