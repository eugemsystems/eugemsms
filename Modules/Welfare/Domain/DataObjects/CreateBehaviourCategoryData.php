<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

final readonly class CreateBehaviourCategoryData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $polarity,
        public int $defaultPoints,
        public ?int $severityLevel = null,
        public bool $requiresEvidence = false,
        public bool $requiresHeadReview = false,
        public bool $autoNotifyGuardian = false,
        public ?int $suggestsSanctionId = null,
        public bool $isSafeguardingTrigger = false,
        public int $sortOrder = 0,
    ) {}
}
