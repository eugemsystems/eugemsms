<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordWaterQualityTestData
{
    public function __construct(
        public int $waterSourceId,
        public CarbonInterface $testedOn,
        public string $qualityStatus,
    ) {}
}
