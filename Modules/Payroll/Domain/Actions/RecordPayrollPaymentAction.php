<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Payroll\Domain\DataObjects\RecordPayrollPaymentData;
use Modules\Payroll\Models\PayrollRun;

/**
 * ACT-RecordPayrollPayment (Book H3 PPL-05 §4, lifecycle step 7 —
 * "PAY: bank file exported; payment recorded"). The bank file itself
 * is produced and uploaded through `Core\Domain\Actions\Files\
 * UploadFileAction` (`payroll_bank_file` category) exactly like any
 * other export in this codebase — this action's own job is only the
 * run-state transition once that file exists, matching
 * `PayrollRun`'s own `MUTABLE_AFTER_POSTED = ['status',
 * 'bank_file_id']` allowance.
 */
final class RecordPayrollPaymentAction extends Action
{
    public function execute(RecordPayrollPaymentData $data): PayrollRun
    {
        $run = PayrollRun::findOrFail($data->payrollRunId);

        if ($run->status !== 'posted') {
            throw new InvalidStateTransitionException(
                "A payroll run can only be marked paid from [posted]; this one is [{$run->status}].",
                ['status' => $run->status],
            );
        }

        return $this->transaction(fn (): PayrollRun => tap($run)->update([
            'status' => 'paid',
            'bank_file_id' => $data->bankFileId,
        ]));
    }
}
