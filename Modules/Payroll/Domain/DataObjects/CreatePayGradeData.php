<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\DataObjects;

final readonly class CreatePayGradeData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $category,
        public string $currency,
        public ?int $minSalaryMinor = null,
        public ?int $maxSalaryMinor = null,
        public ?string $necGradeReference = null,
    ) {}
}
