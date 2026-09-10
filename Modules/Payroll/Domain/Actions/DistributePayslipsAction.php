<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Payroll\Domain\Events\PayslipsDistributed;
use Modules\Payroll\Models\PayrollRun;

/**
 * ACT-DistributePayslips (Book H3 PPL-05 §4, the "distribute" step).
 * Marks every payslip on a posted run as released to staff
 * self-service. The actual self-service surface (§7's API,
 * notification delivery) is out of scope for this pass — this
 * Action only records the release.
 */
final class DistributePayslipsAction extends Action
{
    public function execute(int $payrollRunId): PayrollRun
    {
        $run = PayrollRun::with('payslips')->findOrFail($payrollRunId);

        if ($run->status !== 'posted') {
            throw new InvalidStateTransitionException(
                "Payslips can only be distributed from a [posted] run; this one is [{$run->status}].",
                ['status' => $run->status],
            );
        }

        return $this->transaction(function () use ($run): PayrollRun {
            $run->payslips->each(fn ($payslip) => $payslip->update(['distributed_at' => Carbon::now()]));

            event(new PayslipsDistributed($run));

            return $run;
        });
    }
}
