<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Payroll\Domain\DataObjects\ApprovePayrollRunData;
use Modules\Payroll\Domain\Events\PayrollApproved;
use Modules\Payroll\Models\PayrollRun;

/**
 * ACT-ApprovePayrollRun (Book H3 PPL-05 §4/BR-PPL-05-013,
 * AC-PPL-05-005). `payroll.require_separate_approver` (locked true)
 * would refuse the same user who computed the run from also
 * approving it — permission/identity enforcement itself is out of
 * scope for this pass, matching this codebase's established
 * convention that a permission check is the caller's job, not an
 * Action's.
 */
final class ApprovePayrollRunAction extends Action
{
    public function execute(ApprovePayrollRunData $data): PayrollRun
    {
        $run = PayrollRun::findOrFail($data->payrollRunId);

        if ($run->status !== 'preview') {
            throw new InvalidStateTransitionException(
                "A payroll run can only be approved from [preview]; this one is [{$run->status}].",
                ['status' => $run->status],
            );
        }

        return $this->transaction(function () use ($run, $data): PayrollRun {
            $run->update(['status' => 'approved', 'approved_by' => $data->approvedByUserId]);

            event(new PayrollApproved($run));

            return $run;
        });
    }
}
