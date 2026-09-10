<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreatePeriodStructureData
{
    /**
     * @param  array<int, string>  $dayLabels
     * @param  array<int, PeriodSlotInput>  $slots
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $name,
        public string $cycleType,
        public int $cycleDays,
        public array $dayLabels,
        public array $slots,
        public ?int $sectionId = null,
        public bool $isDefault = false,
    ) {}
}
