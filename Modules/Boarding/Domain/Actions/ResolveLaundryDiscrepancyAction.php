<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\ResolveLaundryDiscrepancyData;
use Modules\Boarding\Models\LaundryItem;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\CreateAdHocChargeAction;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;

/**
 * ACT-ResolveLaundryDiscrepancy (Book F BRD-05 §3/BR-BRD-05-005). A
 * flagged shortfall is closed either by resolving it without charge
 * (item found, tally corrected) or by raising a `FIN-02` ad hoc charge
 * for the missing item — both are terminal for the row.
 */
final class ResolveLaundryDiscrepancyAction extends Action
{
    public function __construct(
        private readonly CreateAdHocChargeAction $createAdHocCharge,
    ) {}

    public function execute(ResolveLaundryDiscrepancyData $data): LaundryItem
    {
        $laundryItem = LaundryItem::findOrFail($data->laundryItemId);

        return $this->transaction(function () use ($laundryItem, $data): LaundryItem {
            if ($data->resolution === 'charged' && $data->feeComponentId !== null && $data->chargeAmountMinor !== null && $data->approvedByUserId !== null) {
                $cycle = $laundryItem->cycle;
                $term = Term::findOrFail($cycle->term_id);

                $this->createAdHocCharge->execute(new CreateAdHocChargeData(
                    schoolId: $laundryItem->school_id,
                    academicYearId: $term->academic_year_id,
                    termId: $cycle->term_id,
                    studentId: $laundryItem->student_id,
                    componentId: $data->feeComponentId,
                    description: 'Laundry item not returned',
                    unitRateMinor: $data->chargeAmountMinor,
                    currency: 'USD',
                    raisedByUserId: $data->approvedByUserId,
                    sourceType: 'laundry_item',
                    sourceId: $laundryItem->id,
                    approvedByUserId: $data->approvedByUserId,
                ));
            }

            return tap($laundryItem)->update(['resolved' => true]);
        });
    }
}
