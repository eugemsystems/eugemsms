<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class PurchasePrepaidTokenData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $meterId,
        public CarbonInterface $purchasedAt,
        public string $tokenNumber,
        public int $amountPaidMinor,
        public string $currency,
        public float $unitsPurchased,
        public int $prepaidAssetAccountId,
        public int $contraAccountId,
        public int $purchasedByUserId,
        public int $leviesMinor = 0,
        public ?string $vendor = null,
    ) {}
}
