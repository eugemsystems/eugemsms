<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateBorrowerCategoryData;
use Modules\Academic\Models\BorrowerCategory;
use Modules\Core\Domain\Actions\Action;

final class CreateBorrowerCategoryAction extends Action
{
    public function execute(CreateBorrowerCategoryData $data): BorrowerCategory
    {
        return $this->transaction(fn (): BorrowerCategory => BorrowerCategory::create([
            'school_id' => $data->schoolId,
            'category' => $data->category,
            'max_concurrent_loans' => $data->maxConcurrentLoans,
            'loan_period_days' => $data->loanPeriodDays,
            'max_renewals' => $data->maxRenewals,
        ]));
    }
}
