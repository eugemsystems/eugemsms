<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordPriorSchoolData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public string $schoolName,
        public ?string $schoolType = null,
        public string $country = 'ZW',
        public ?string $province = null,
        public ?CarbonInterface $attendedFrom = null,
        public ?CarbonInterface $attendedTo = null,
        public ?string $lastGradeCompleted = null,
        public ?string $reasonForLeaving = null,
        public ?int $transferLetterFileId = null,
        public bool $hadOutstandingFees = false,
        public ?string $notes = null,
    ) {}
}
