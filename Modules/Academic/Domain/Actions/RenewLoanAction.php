<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\RenewLoanData;
use Modules\Academic\Domain\Exceptions\RenewalNotAllowedException;
use Modules\Academic\Models\BorrowerCategory;
use Modules\Academic\Models\Loan;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RenewLoan (Book K ACA-10 §4/BR-ACA-10-003). No reservation
 * queue table exists in this pass's schema despite `library_copies
 * .status` including `reserved` as a possible value — renewal here
 * only checks `max_renewals`, not "is a reservation waiting".
 * `max_renewals` comes from the `borrower_categories` row matching
 * the loan's own `borrower_category` (captured at issue time — see
 * the `loans` migration's docblock), never re-derived from the
 * borrower's current category.
 */
final class RenewLoanAction extends Action
{
    public function execute(RenewLoanData $data): Loan
    {
        $loan = Loan::findOrFail($data->loanId);

        if ($loan->status !== 'active') {
            throw RenewalNotAllowedException::forLoan($loan->id, "loan is [{$loan->status}], not active");
        }

        $category = BorrowerCategory::query()
            ->where('school_id', $loan->school_id)
            ->where('category', $loan->borrower_category)
            ->firstOrFail();

        if ($loan->renewal_count >= $category->max_renewals) {
            throw RenewalNotAllowedException::forLoan($loan->id, "already renewed the maximum of {$category->max_renewals} time(s)");
        }

        $periodDays = max(1, (int) $loan->issued_on->diffInDays($loan->due_on));

        return $this->transaction(function () use ($loan, $periodDays): Loan {
            $loan->update([
                'renewal_count' => $loan->renewal_count + 1,
                'due_on' => Carbon::today()->addDays($periodDays),
            ]);

            return $loan->fresh();
        });
    }
}
