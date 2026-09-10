<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordRoomInspectionData
{
    /**
     * @param  array<string, int>  $criteriaScores
     * @param  array<int, int>|null  $photoFileIds
     */
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $roomId,
        public CarbonInterface $inspectionDate,
        public string $inspectionType,
        public array $criteriaScores,
        public float $maxScore,
        public int $inspectorStaffId,
        public ?string $findings = null,
        public ?array $photoFileIds = null,
        public bool $followUpRequired = false,
    ) {}
}
