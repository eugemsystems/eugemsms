<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class CreateDiscountSchemeData
{
    /**
     * @param  array<int, int>|null  $appliesToComponents
     * @param  array<int, array{nth: int, percent: string}>|null  $tierBands
     */
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $schemeType,
        public string $category,
        public string $calculationMethod,
        public int $contraAccountId,
        public ?array $appliesToComponents = null,
        public ?string $defaultPercent = null,
        public ?int $defaultAmountMinor = null,
        public ?string $currency = null,
        public ?array $tierBands = null,
        public bool $requiresMeansAssessment = false,
        public bool $requiresAcademicThreshold = false,
        public ?string $minimumAveragePercent = null,
        public bool $requiresApproval = true,
        public ?int $approvalChainId = null,
        public bool $isSponsorFunded = false,
        public ?string $renewalFrequency = null,
    ) {}
}
