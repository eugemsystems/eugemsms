<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordLivestockEventData
{
    public function __construct(
        public int $schoolId,
        public int $livestockId,
        public string $eventType,
        public CarbonInterface $eventDate,
        public int $recordedByUserId,
        public int $headCountAffected = 1,
        public ?string $description = null,
        public ?string $medication = null,
        public ?string $dosage = null,
        public ?int $withdrawalPeriodDays = null,
        public ?float $weightKg = null,
        public ?int $costMinor = null,
        public ?string $performedBy = null,
        public ?int $academicYearId = null,
        public ?int $termId = null,
        public ?int $disposalApprovedByUserId = null,
    ) {}
}
