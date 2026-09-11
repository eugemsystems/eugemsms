<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

final readonly class AdvanceFeatureRolloutStageData
{
    /**
     * @param  array<int, int>|null  $cohortTenantIds  required when advancing into the `cohort` stage
     */
    public function __construct(
        public int $rolloutId,
        public ?array $cohortTenantIds = null,
        public ?int $percentage = null,
    ) {}
}
