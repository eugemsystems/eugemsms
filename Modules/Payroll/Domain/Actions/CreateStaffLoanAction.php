<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Payroll\Domain\DataObjects\CreateStaffLoanData;
use Modules\Payroll\Models\StaffLoan;

/**
 * ACT-CreateStaffLoan (Book H3 PPL-05 §2/BR-PPL-05-018/019). A
 * `fee_offset` loan's `offsetStudentIds` names which learner(s) its
 * instalment credits at posting time (`PostPayrollRunAction`) — split
 * evenly when more than one.
 */
final class CreateStaffLoanAction extends Action
{
    public function execute(CreateStaffLoanData $data): StaffLoan
    {
        return $this->transaction(fn (): StaffLoan => StaffLoan::create([
            'school_id' => $data->schoolId,
            'staff_id' => $data->staffId,
            'loan_type' => $data->loanType,
            'principal_minor' => $data->principalMinor,
            'currency' => $data->currency,
            'interest_rate_percent' => $data->interestRatePercent,
            'instalment_minor' => $data->instalmentMinor,
            'instalment_count' => $data->instalmentCount,
            'starts_on' => $data->startsOn->toDateString(),
            'outstanding_minor' => $data->principalMinor,
            'paid_minor' => 0,
            'offset_student_ids' => $data->offsetStudentIds,
            'approved_by' => $data->approvedByUserId,
            'status' => 'active',
        ]));
    }
}
