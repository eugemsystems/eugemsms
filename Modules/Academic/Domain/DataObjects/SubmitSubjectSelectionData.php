<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class SubmitSubjectSelectionData
{
    /**
     * @param  array<int, int>  $selectedSubjectIds
     * @param  array<int, int>|null  $reserveSubjectIds
     */
    public function __construct(
        public int $studentId,
        public int $academicYearId,
        public int $gradeLevelId,
        public array $selectedSubjectIds,
        public int $submittedByUserId,
        public ?int $pathwayId = null,
        public ?array $reserveSubjectIds = null,
        public ?int $indicativeFeeMinor = null,
        public ?string $indicativeFeeCurrency = null,
        public bool $acknowledgeWarnings = false,
    ) {}
}
