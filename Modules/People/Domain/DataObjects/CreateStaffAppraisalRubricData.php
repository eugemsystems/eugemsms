<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class CreateStaffAppraisalRubricData
{
    /**
     * @param  array<int, array<string, mixed>>  $criteria  [{criterion, descriptor_levels}]
     */
    public function __construct(
        public int $schoolId,
        public string $name,
        public array $criteria,
    ) {}
}
