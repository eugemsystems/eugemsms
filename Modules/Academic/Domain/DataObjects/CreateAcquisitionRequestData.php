<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateAcquisitionRequestData
{
    public function __construct(
        public int $schoolId,
        public string $requestedTitle,
        public int $requestedByUserId,
        public int $copiesRequested = 1,
        public ?int $estimatedCostMinor = null,
    ) {}
}
