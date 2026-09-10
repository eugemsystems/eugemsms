<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\DataObjects;

final readonly class CreatePayComponentData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $componentType,
        public string $category,
        public string $calculationMethod,
        public ?int $defaultAmountMinor = null,
        public ?string $defaultPercent = null,
        public ?string $currency = null,
        public bool $isTaxable = true,
        public bool $isPensionable = true,
        public bool $isNecApplicable = true,
        public bool $isZimdefApplicable = true,
        public string $taxablePercent = '100',
        public ?int $expenseAccountId = null,
        public ?int $liabilityAccountId = null,
        public string $costCentreSource = 'staff',
    ) {}
}
