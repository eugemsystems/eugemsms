<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordLessonObservationData
{
    /**
     * @param  array<string, string>  $scores  criterion => level
     */
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $observedStaffId,
        public int $observerStaffId,
        public int $rubricId,
        public CarbonInterface $observedAt,
        public array $scores,
        public ?string $classObserved = null,
        public ?int $subjectId = null,
        public ?string $strengthsNoted = null,
        public ?string $areasForDevelopment = null,
        public ?string $overallRating = null,
        public ?int $followUpObservationId = null,
    ) {}
}
