<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

/**
 * Book E ACA-03 §4 step 1 — one line of the requirement list the
 * generator builds from `PPL-04`'s `TeacherAllocation` rows: this
 * class, this subject, this teacher, this many periods a week.
 * Teaching-group (set) requirements are deferred — `TeachingGroup` has
 * no `periods_per_week` column yet to derive them from; see
 * `BuildTimetableRequirementsAction`'s own docblock.
 */
final readonly class TimetableRequirement
{
    public function __construct(
        public int $subjectId,
        public int $staffId,
        public int $periodsPerWeek,
        public ?int $classId = null,
        public ?int $teachingGroupId = null,
    ) {}

    public function label(): string
    {
        return $this->classId !== null
            ? "class:{$this->classId}:subject:{$this->subjectId}"
            : "group:{$this->teachingGroupId}:subject:{$this->subjectId}";
    }
}
