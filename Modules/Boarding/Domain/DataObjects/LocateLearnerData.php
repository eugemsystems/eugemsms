<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class LocateLearnerData
{
    public function __construct(
        public int $incidentId,
        public int $locatedByUserId,
        public string $locationFound,
        public string $outcome,
        public ?string $outcomeNote = null,
    ) {}
}
