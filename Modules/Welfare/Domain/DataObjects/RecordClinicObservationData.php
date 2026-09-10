<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordClinicObservationData
{
    public function __construct(
        public int $schoolId,
        public int $admissionId,
        public CarbonInterface $observedAt,
        public int $observedByUserId,
        public ?float $temperatureC = null,
        public ?int $pulseBpm = null,
        public ?int $respirationRate = null,
        public ?string $bloodPressure = null,
        public ?int $oxygenSaturation = null,
        public ?int $painScore = null,
        public ?string $notes = null,
    ) {}
}
