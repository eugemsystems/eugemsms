<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Domain\DataObjects\ApprovePaymentPlanData;
use Modules\Finance\Models\PaymentPlan;

/**
 * ACT-ApprovePaymentPlan (Book B FIN-03 §2/BR-FIN-03-016/017). Moves
 * straight from `proposed` to `active` — the instalment schedule is
 * already fully built at creation, so there is no separate "approved
 * but not yet started" state worth persisting; a plan is either still
 * a proposal or it is in force. Reminders are suppressed for invoices
 * covered by an active, non-breached plan the moment this runs.
 */
final class ApprovePaymentPlanAction extends Action
{
    public function execute(ApprovePaymentPlanData $data): PaymentPlan
    {
        $plan = PaymentPlan::findOrFail($data->paymentPlanId);

        if ($plan->status !== 'proposed') {
            throw new InvalidStateTransitionException(
                "A payment plan can only be approved from [proposed] — this one is [{$plan->status}].",
                ['payment_plan_id' => $plan->id],
            );
        }

        return $this->transaction(fn (): PaymentPlan => tap($plan)->update([
            'status' => 'active',
            'approved_by' => $data->approvedByUserId,
        ]));
    }
}
