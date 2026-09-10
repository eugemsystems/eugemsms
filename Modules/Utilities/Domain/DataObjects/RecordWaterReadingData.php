<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordWaterReadingData
{
    public function __construct(
        public int $schoolId,
        public int $waterSourceId,
        public CarbonInterface $readOn,
        public int $readByUserId,
        public ?float $storageLevelPercent = null,
        public ?float $volumePumpedLitres = null,
        public ?float $pumpHours = null,
        public ?float $yieldObserved = null,
        public ?string $notes = null,
        public ?int $academicYearId = null,
        public ?int $termId = null,
        public ?int $costCentreId = null,
    ) {}
}
