<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\CreateBorrowerCategoryData;
use Modules\Academic\Models\BorrowerCategory;
use Modules\Core\Domain\Actions\Action;

final class CreateBorrowerCategoryAction extends Action
{
    public function execute(CreateBorrowerCategoryData $data): BorrowerCategory
    {
        $category = trim($data->category);

        if ($category === '' || strlen($category) > 20) {
            throw new InvalidArgumentException('A borrower category needs a name of up to 20 characters.');
        }

        if ($data->maxConcurrentLoans < 1 || $data->loanPeriodDays < 1 || $data->maxRenewals < 0 || $data->maxConcurrentLoans > 100 || $data->loanPeriodDays > 400 || $data->maxRenewals > 20) {
            throw new InvalidArgumentException('Loan limits, period and renewals are out of range.');
        }

        if (BorrowerCategory::query()->where('school_id', $data->schoolId)->where('category', $category)->exists()) {
            throw new InvalidArgumentException("Borrower category [{$category}] already exists.");
        }

        return $this->transaction(fn (): BorrowerCategory => BorrowerCategory::create([
            'school_id' => $data->schoolId,
            'category' => $category,
            'max_concurrent_loans' => $data->maxConcurrentLoans,
            'loan_period_days' => $data->loanPeriodDays,
            'max_renewals' => $data->maxRenewals,
        ]));
    }
}
