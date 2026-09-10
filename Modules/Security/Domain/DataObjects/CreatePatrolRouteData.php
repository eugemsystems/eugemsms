<?php

declare(strict_types=1);

namespace Modules\Security\Domain\DataObjects;

final readonly class CreatePatrolRouteData
{
    /**
     * @param  array<int, int>  $checkpointIds
     */
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public array $checkpointIds,
        public string $frequency,
        public ?int $expectedDurationMin = null,
    ) {}
}
