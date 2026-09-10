<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordSolarGenerationData
{
    public function __construct(
        public int $schoolId,
        public int $installationId,
        public CarbonInterface $recordDate,
        public float $kwhGenerated,
        public string $readingMethod,
        public ?float $kwhConsumed = null,
        public ?float $batteryStatePercent = null,
        public ?int $recordedByUserId = null,
    ) {}
}
