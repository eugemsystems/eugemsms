<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\RecordPaymentPlanInstalmentPaymentData;
use Modules\Finance\Models\PaymentPlan;
use Modules\Finance\Models\PaymentPlanInstalment;

/**
 * ACT-RecordPaymentPlanInstalmentPayment (Book B FIN-03 §2). The spec
 * describes `payment_plan_instalments.paid_minor` as "a cache updated
 * as FIN-04 receipts allocate against the plan's covered invoices" —
 * wiring that automatically requires teaching `InvoiceAllocationEngine`
 * which invoices belong to which plan, a change to FIN-04's own
 * engine, not a FIN-03 domain gap. Until that wiring exists, a bursar
 * who sees a receipt land against a plan's invoice records it against
 * the matching instalment here. Marks the plan `completed` once every
 * instalment is fully paid.
 */
final class RecordPaymentPlanInstalmentPaymentAction extends Action
{
    public function execute(RecordPaymentPlanInstalmentPaymentData $data): PaymentPlanInstalment
    {
        $instalment = PaymentPlanInstalment::findOrFail($data->instalmentId);

        return $this->transaction(function () use ($instalment, $data): PaymentPlanInstalment {
            $newPaidMinor = $instalment->paid_minor + $data->paidMinor;

            $instalment->update([
                'paid_minor' => $newPaidMinor,
                'status' => $newPaidMinor >= $instalment->amount_minor ? 'paid' : 'partial',
            ]);

            $plan = PaymentPlan::with('instalments')->findOrFail($instalment->plan_id);

            if ($plan->instalments->every(fn (PaymentPlanInstalment $i): bool => $i->status === 'paid')) {
                $plan->update(['status' => 'completed']);
            }

            return $instalment;
        });
    }
}
