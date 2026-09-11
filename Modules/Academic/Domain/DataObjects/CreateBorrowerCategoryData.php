<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateBorrowerCategoryData
{
    public function __construct(
        public int $schoolId,
        public string $category,
        public int $maxConcurrentLoans,
        public int $loanPeriodDays,
        public int $maxRenewals = 1,
    ) {}
}
