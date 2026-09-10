<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\DataObjects;

final readonly class CreateUtilityAccountData
{
    public function __construct(
        public int $schoolId,
        public string $utilityType,
        public string $provider,
        public string $accountNumber,
        public string $billingMode,
        public int $costCentreId,
        public int $expenseAccountId,
        public ?string $tariffCode = null,
    ) {}
}
