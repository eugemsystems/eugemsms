<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\DataObjects;

final readonly class CreateSpendPointData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $pointType,
        public int $incomeAccountId,
        public int $costCentreId,
        public ?int $storeId = null,
        public ?int $tillId = null,
        public bool $isFiscalisable = true,
    ) {}
}
