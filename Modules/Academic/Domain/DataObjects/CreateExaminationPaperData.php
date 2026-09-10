<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateExaminationPaperData
{
    public function __construct(
        public int $schoolId,
        public int $sessionId,
        public int $subjectId,
        public int $gradeLevelId,
        public string $paperNumber,
        public string $paperName,
        public string $componentType,
        public float $maxMark,
        public float $weightPercent,
        public int $durationMinutes,
        public ?CarbonInterface $scheduledDate = null,
        public ?string $scheduledStart = null,
        public ?string $requiresSpecialVenue = null,
        public ?int $setterStaffId = null,
    ) {}
}
