<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateAwardData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $studentId,
        public string $awardType,
        public string $title,
        public CarbonInterface $awardedOn,
        public int $awardedByUserId,
        public ?int $activityId = null,
        public ?string $citation = null,
        public bool $appearsOnReportCard = true,
        public bool $appearsOnTranscript = true,
    ) {}
}
