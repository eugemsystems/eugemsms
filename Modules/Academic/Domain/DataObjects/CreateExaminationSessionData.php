<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateExaminationSessionData
{
    /**
     * @param  array<int, int>  $affectedLevels
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public string $name,
        public string $examType,
        public string $examBody,
        public array $affectedLevels,
        public CarbonInterface $startsOn,
        public CarbonInterface $endsOn,
        public int $createdBy,
        public ?string $indexNumberPattern = null,
        public ?int $examSlotPlanId = null,
    ) {}
}
