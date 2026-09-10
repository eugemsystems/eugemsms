<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

final readonly class CreateBudgetData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $name,
        public string $budgetType,
        public string $periodBasis,
        public string $currency,
        public int $preparedByUserId,
    ) {}
}
