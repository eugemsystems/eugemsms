<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordInsurancePolicyData
{
    /**
     * @param  array<int, int>|null  $coveredAssetIds
     */
    public function __construct(
        public int $schoolId,
        public string $policyNumber,
        public string $insurer,
        public string $policyType,
        public int $sumInsuredMinor,
        public string $currency,
        public int $premiumMinor,
        public CarbonInterface $startsOn,
        public CarbonInterface $expiresOn,
        public ?array $coveredAssetIds = null,
        public ?int $categoryId = null,
    ) {}
}
