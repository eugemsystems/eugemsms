<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateLessonPlanData
{
    public function __construct(
        public int $schoolId,
        public int $teacherStaffId,
        public CarbonInterface $lessonDate,
        public string $topic,
        public ?int $schemeOfWorkId = null,
        public ?int $timetableSlotId = null,
        public ?string $objectives = null,
        public ?string $activities = null,
        public ?string $resourcesNeeded = null,
        public ?string $differentiationNotes = null,
    ) {}
}
