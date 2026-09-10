<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class MarkProjectData
{
    /**
     * @param  array<int, CriterionMarkInput>  $criterionMarks
     */
    public function __construct(
        public int $learnerProjectId,
        public int $markerStaffId,
        public array $criterionMarks,
        public ?string $markerComment = null,
    ) {}
}
