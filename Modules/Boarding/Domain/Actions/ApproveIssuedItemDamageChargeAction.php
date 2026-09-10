<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\ApproveIssuedItemChargeData;
use Modules\Boarding\Models\LearnerIssuedItem;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\CreateAdHocChargeAction;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;

/**
 * ACT-ApproveIssuedItemDamageCharge (Book F BRD-05 §3/BR-BRD-05-003).
 * The only path from a `damaged` item to a `FIN-02` ad hoc charge —
 * mirrors `ApproveHostelDamageChargeAction`'s report-then-approve shape.
 */
final class ApproveIssuedItemDamageChargeAction extends Action
{
    public function __construct(
        private readonly CreateAdHocChargeAction $createAdHocCharge,
    ) {}

    public function execute(ApproveIssuedItemChargeData $data): LearnerIssuedItem
    {
        $issuedItem = LearnerIssuedItem::findOrFail($data->learnerIssuedItemId);

        if ($issuedItem->status !== 'damaged' || $issuedItem->ad_hoc_charge_id !== null) {
            throw new InvalidStateTransitionException(
                "Item #{$issuedItem->id} must be damaged and uncharged to approve a charge.",
                ['learner_issued_item_id' => $issuedItem->id, 'status' => $issuedItem->status],
            );
        }

        $amount = $data->chargeAmountMinor ?? $issuedItem->issuableItem->replacement_cost_minor;
        $term = Term::findOrFail($issuedItem->term_id);

        return $this->transaction(function () use ($issuedItem, $data, $amount, $term): LearnerIssuedItem {
            $charge = $this->createAdHocCharge->execute(new CreateAdHocChargeData(
                schoolId: $issuedItem->school_id,
                academicYearId: $term->academic_year_id,
                termId: $issuedItem->term_id,
                studentId: $issuedItem->student_id,
                componentId: $data->feeComponentId,
                description: "Linen/kit damage: {$issuedItem->issuableItem->name}",
                unitRateMinor: (int) $amount,
                currency: $issuedItem->issuableItem->currency,
                raisedByUserId: $data->approvedByUserId,
                sourceType: 'learner_issued_item',
                sourceId: $issuedItem->id,
                approvedByUserId: $data->approvedByUserId,
            ));

            return tap($issuedItem)->update([
                'charge_minor' => (int) $amount,
                'ad_hoc_charge_id' => $charge->id,
            ]);
        });
    }
}
