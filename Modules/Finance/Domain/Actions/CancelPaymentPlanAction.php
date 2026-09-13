<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Domain\DataObjects\CancelPaymentPlanData;
use Modules\Finance\Models\PaymentPlan;

final class CancelPaymentPlanAction extends Action
{
    public function execute(CancelPaymentPlanData $data): PaymentPlan
    {
        $plan = PaymentPlan::findOrFail($data->paymentPlanId);

        if (! in_array($plan->status, ['proposed', 'active', 'breached'], true)) {
            throw new InvalidStateTransitionException(
                "A payment plan in [{$plan->status}] cannot be cancelled.",
                ['payment_plan_id' => $plan->id],
            );
        }

        return $this->transaction(fn (): PaymentPlan => tap($plan)->update(['status' => 'cancelled']));
    }
}
