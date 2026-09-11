<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class CreateBudgetEnvelopeData
{
    public function __construct(
        public int $schoolId,
        public int $schemeId,
        public int $academicYearId,
        public ?int $budgetMinor = null,
        public ?string $currency = null,
    ) {}
}
