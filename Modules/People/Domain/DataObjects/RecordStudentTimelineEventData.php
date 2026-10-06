<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordStudentTimelineEventData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public string $eventCategory,
        public string $eventType,
        public string $title,
        public ?string $summary = null,
        public ?string $severity = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public bool $visibleToGuardian = false,
        public ?CarbonInterface $occurredAt = null,
        public ?int $recordedByUserId = null,
        public ?int $academicYearId = null,
        public ?int $termId = null,
    ) {}
}
