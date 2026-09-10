<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class AssignToTeachingGroupData
{
    public function __construct(
        public int $studentId,
        public int $teachingGroupId,
        public ?CarbonInterface $effectiveFrom = null,
        public bool $acknowledgeCapacityWarning = false,
    ) {}
}
