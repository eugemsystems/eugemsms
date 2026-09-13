<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\DataObjects\CreatePaymentPlanData;
use Modules\Finance\Models\PaymentPlan;
use Modules\Finance\Models\PaymentPlanInstalment;

/**
 * ACT-CreatePaymentPlan (Book B FIN-03 §2/§5 `Finance\PaymentPlans\Index`).
 * Splits the total evenly across `instalmentCount` monthly instalments
 * via `Money::allocate()` — the same rounding-remainder-to-the-first-
 * share mechanism `InvoiceAllocationEngine` (FIN-04) already uses, so
 * the instalments always sum back to exactly `totalMinor`. Lands
 * `proposed`; `ApprovePaymentPlanAction` is what makes it `active`.
 */
final class CreatePaymentPlanAction extends Action
{
    public function execute(CreatePaymentPlanData $data): PaymentPlan
    {
        return $this->transaction(function () use ($data): PaymentPlan {
            $plan = PaymentPlan::create([
                'school_id' => $data->schoolId,
                'student_id' => $data->studentId,
                'party_type' => $data->partyType,
                'party_id' => $data->partyId,
                'total_minor' => $data->totalMinor,
                'currency' => $data->currency,
                'instalment_count' => $data->instalmentCount,
                'status' => 'proposed',
                'created_by' => $data->createdByUserId,
            ]);

            $shares = Money::of($data->totalMinor, Currency::from($data->currency))
                ->allocate(array_fill(0, $data->instalmentCount, 1));

            foreach (array_values($shares) as $index => $share) {
                PaymentPlanInstalment::create([
                    'plan_id' => $plan->id,
                    'instalment_number' => $index + 1,
                    'due_date' => $data->firstDueDate->copy()->addMonthsNoOverflow($index)->toDateString(),
                    'amount_minor' => $share->minor,
                    'status' => 'pending',
                ]);
            }

            return $plan->load('instalments');
        });
    }
}
