<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class CreateAllocationConstraintData
{
    /**
     * @param  array<int, int>|null  $gradeLevelIds
     */
    public function __construct(
        public int $schoolId,
        public string $constraintType,
        public string $severity,
        public int $weight = 1,
        public ?int $hostelId = null,
        public ?array $gradeLevelIds = null,
        public ?int $value = null,
        public ?string $reason = null,
    ) {}
}
