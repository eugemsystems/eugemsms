<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Domain\DataObjects\ApproveBillingRunData;
use Modules\Finance\Domain\Events\BillingRunApproved;
use Modules\Finance\Models\BillingRun;
use Modules\Finance\Models\LearnerFeeAssignment;

/**
 * ACT-ApproveBillingRun (Book B FIN-02 §3/BR-FIN-02-013 ⭐). The human
 * gate between `preview` and `committed` — a run cannot skip this
 * state (AC-FIN-02-006).
 */
final class ApproveBillingRunAction extends Action
{
    public function execute(ApproveBillingRunData $data): BillingRun
    {
        $run = BillingRun::findOrFail($data->billingRunId);

        if ($run->status !== 'preview') {
            throw new InvalidStateTransitionException(
                "A billing run can only be approved from [preview]; this one is [{$run->status}].",
                ['status' => $run->status],
            );
        }

        return $this->transaction(function () use ($run, $data): BillingRun {
            $run->update([
                'status' => 'approved',
                'approved_by' => $data->approvedByUserId,
                'approved_at' => Carbon::now(),
            ]);

            LearnerFeeAssignment::query()
                ->where('billing_run_id', $run->id)
                ->where('status', 'draft')
                ->update(['status' => 'approved']);

            event(new BillingRunApproved($run));

            return $run;
        });
    }
}
