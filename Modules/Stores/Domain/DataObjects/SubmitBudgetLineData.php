<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

final readonly class SubmitBudgetLineData
{
    public function __construct(
        public int $budgetId,
        public int $accountId,
        public int $costCentreId,
        public int $annualAmountMinor,
        public ?int $termId = null,
        public ?int $term1Minor = null,
        public ?int $term2Minor = null,
        public ?int $term3Minor = null,
        public ?string $basisNote = null,
        public ?int $priorYearActualMinor = null,
    ) {}
}
