<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class AssignInvigilationData
{
    public function __construct(
        public int $paperId,
        public int $venueId,
        public int $staffId,
        public string $role = 'chief',
        public ?int $dutyAssignmentId = null,
        public bool $overrideSubjectTeacherExclusion = false,
    ) {}
}
