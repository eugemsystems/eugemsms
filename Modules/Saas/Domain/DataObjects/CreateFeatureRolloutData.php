<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

final readonly class CreateFeatureRolloutData
{
    /**
     * @param  array<int, int>  $pilotTenantIds
     */
    public function __construct(
        public string $featureFlagKey,
        public array $pilotTenantIds,
        public int $startedBy,
        public ?string $notes = null,
    ) {}
}
