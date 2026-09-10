<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateTeachingGroupData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $subjectId,
        public int $gradeLevelId,
        public string $code,
        public string $name,
        public ?string $setLevel = null,
        public ?int $teacherStaffId = null,
        public ?int $roomId = null,
        public ?int $capacity = null,
    ) {}
}
