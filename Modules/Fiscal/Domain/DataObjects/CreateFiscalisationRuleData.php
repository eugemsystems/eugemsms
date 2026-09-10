<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\DataObjects;

final readonly class CreateFiscalisationRuleData
{
    public function __construct(
        public int $schoolId,
        public string $ruleName,
        public string $sourceType,
        public bool $isFiscalisable,
        public string $taxType,
        public string $rationale,
        public int $reviewedByUserId,
        public ?string $sourceIdentifier = null,
        public string $taxRatePercent = '0',
        public ?string $taxCode = null,
        public int $priority = 100,
    ) {}
}
