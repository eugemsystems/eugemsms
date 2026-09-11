<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\ReturnLoanData;
use Modules\Academic\Models\Loan;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Finance\Domain\Actions\CreateAdHocChargeAction;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;

/**
 * ACT-ReturnLoan (Book K ACA-10 §4/BR-ACA-10-005 ⭐/006). A late
 * return's fine posts as an ad hoc charge through `FIN-02` directly —
 * the exact same mechanism `BRD-05`'s linen loss uses, not a separate
 * library billing path. The fine is the school's configured daily
 * rate times days late, capped at the item's replacement cost.
 */
final class ReturnLoanAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly CreateAdHocChargeAction $createAdHocCharge,
    ) {}

    public function execute(ReturnLoanData $data): Loan
    {
        $loan = Loan::with(['copy.item'])->findOrFail($data->loanId);

        if ($loan->status !== 'active') {
            throw new InvalidStateTransitionException(
                "Loan #{$loan->id} in [{$loan->status}] cannot be returned.",
                ['loan_id' => $loan->id, 'status' => $loan->status],
            );
        }

        $returnedOn = $data->returnedOn?->toImmutable() ?? Carbon::today();
        $daysLate = max(0, (int) $loan->due_on->diffInDays($returnedOn, false));

        $fineMinor = 0;

        if ($daysLate > 0) {
            $dailyRate = (int) $this->settings->get('library.daily_fine_rate_minor', new ScopeChain(schoolId: $loan->school_id));
            $replacementCost = (int) ($loan->copy->item->replacement_cost_minor ?? PHP_INT_MAX);
            $fineMinor = min($daysLate * $dailyRate, $replacementCost);
        }

        return $this->transaction(function () use ($loan, $data, $returnedOn, $fineMinor): Loan {
            $chargeId = null;

            if ($fineMinor > 0 && $data->feeComponentId !== null) {
                $term = $loan->term;
                $charge = $this->createAdHocCharge->execute(new CreateAdHocChargeData(
                    schoolId: $loan->school_id,
                    academicYearId: $term->academic_year_id,
                    termId: $loan->term_id,
                    studentId: $loan->borrower_id,
                    componentId: $data->feeComponentId,
                    description: "Late return fine: {$loan->copy->item->title}",
                    unitRateMinor: $fineMinor,
                    currency: $loan->copy->item->currency ?? 'USD',
                    raisedByUserId: $data->returnedByUserId,
                    sourceType: 'loan',
                    sourceId: $loan->id,
                    approvedByUserId: $data->returnedByUserId,
                ));

                $chargeId = $charge->id;
            }

            $loan->update([
                'returned_on' => $returnedOn->toDateString(),
                'condition_at_return' => $data->conditionAtReturn,
                'status' => $chargeId !== null ? 'fined' : 'returned',
                'fine_charge_id' => $chargeId,
            ]);

            $loan->copy->update(['status' => 'available', 'condition' => $data->conditionAtReturn]);

            return $loan->fresh();
        });
    }
}
