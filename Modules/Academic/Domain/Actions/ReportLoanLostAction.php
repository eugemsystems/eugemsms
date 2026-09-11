<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\ReportLoanLostData;
use Modules\Academic\Models\Loan;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Domain\Actions\CreateAdHocChargeAction;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;

/**
 * ACT-ReportLoanLost (Book K ACA-10 §4/BR-ACA-10-005 ⭐/006). A
 * lost-book charge is the item's `replacement_cost_minor`, posted the
 * same way as a late fine — one charge mechanism.
 */
final class ReportLoanLostAction extends Action
{
    public function __construct(
        private readonly CreateAdHocChargeAction $createAdHocCharge,
    ) {}

    public function execute(ReportLoanLostData $data): Loan
    {
        $loan = Loan::with(['copy.item', 'term'])->findOrFail($data->loanId);

        if ($loan->status !== 'active') {
            throw new InvalidStateTransitionException(
                "Loan #{$loan->id} in [{$loan->status}] cannot be reported lost.",
                ['loan_id' => $loan->id, 'status' => $loan->status],
            );
        }

        $replacementCost = (int) ($loan->copy->item->replacement_cost_minor ?? 0);

        return $this->transaction(function () use ($loan, $data, $replacementCost): Loan {
            $charge = $this->createAdHocCharge->execute(new CreateAdHocChargeData(
                schoolId: $loan->school_id,
                academicYearId: $loan->term->academic_year_id,
                termId: $loan->term_id,
                studentId: $loan->borrower_id,
                componentId: $data->feeComponentId,
                description: "Lost library item replacement: {$loan->copy->item->title}",
                unitRateMinor: $replacementCost,
                currency: $loan->copy->item->currency ?? 'USD',
                raisedByUserId: $data->chargedByUserId,
                sourceType: 'loan',
                sourceId: $loan->id,
                approvedByUserId: $data->chargedByUserId,
            ));

            $loan->update(['status' => 'lost', 'fine_charge_id' => $charge->id]);
            $loan->copy->update(['status' => 'lost']);

            return $loan->fresh();
        });
    }
}
