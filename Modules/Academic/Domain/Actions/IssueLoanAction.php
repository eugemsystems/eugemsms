<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\IssueLoanData;
use Modules\Academic\Domain\Exceptions\CopyNotAvailableException;
use Modules\Academic\Domain\Exceptions\LoanLimitExceededException;
use Modules\Academic\Models\BorrowerCategory;
use Modules\Academic\Models\LibraryCopy;
use Modules\Academic\Models\Loan;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-IssueLoan (Book K ACA-10 §4/BR-ACA-10-002/AC-ACA-10-003). Loan
 * limits and periods are configured per borrower category, checked at
 * issue. This pass has no "raise the limit for this case with a
 * reason" override path — only the refusal.
 */
final class IssueLoanAction extends Action
{
    public function execute(IssueLoanData $data): Loan
    {
        $copy = LibraryCopy::findOrFail($data->copyId);

        if ($copy->status !== 'available') {
            throw CopyNotAvailableException::forCopy($copy->id);
        }

        $category = BorrowerCategory::query()
            ->where('school_id', $copy->school_id)
            ->where('category', $data->borrowerCategory)
            ->firstOrFail();

        $activeLoanCount = Loan::query()
            ->where('school_id', $copy->school_id)
            ->where('borrower_type', $data->borrowerType)
            ->where('borrower_id', $data->borrowerId)
            ->where('status', 'active')
            ->count();

        if ($activeLoanCount >= $category->max_concurrent_loans) {
            throw LoanLimitExceededException::forBorrower($data->borrowerType, $data->borrowerId, $category->max_concurrent_loans);
        }

        return $this->transaction(function () use ($copy, $data, $category): Loan {
            $copy->update(['status' => 'on_loan']);

            return Loan::create([
                'school_id' => $copy->school_id,
                'term_id' => $data->termId,
                'copy_id' => $copy->id,
                'borrower_type' => $data->borrowerType,
                'borrower_id' => $data->borrowerId,
                'borrower_category' => $data->borrowerCategory,
                'issued_on' => Carbon::today(),
                'due_on' => Carbon::today()->addDays($category->loan_period_days),
                'renewal_count' => 0,
                'status' => 'active',
            ]);
        });
    }
}
