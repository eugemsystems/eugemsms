<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class AssignSubstituteCoverData
{
    public function __construct(
        public int $substitutionId,
        public int $coverStaffId,
        public int $assignedByUserId,
        public ?string $workSet = null,
    ) {}
}
