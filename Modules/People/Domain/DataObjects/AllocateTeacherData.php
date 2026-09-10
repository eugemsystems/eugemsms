<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class AllocateTeacherData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $staffId,
        public int $subjectId,
        public int $classId,
        public int $weeklyPeriods,
        public int $allocatedByUserId,
        public string $role = 'teacher',
        public bool $isClassTeacher = false,
        public ?CarbonInterface $startsOn = null,
        public bool $overrideCeiling = false,
    ) {}
}
