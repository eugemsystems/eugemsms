<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\DataObjects;

final readonly class CreateTransportZoneData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public int $termlyFeeMinor,
        public string $currency,
        public ?float $maxDistanceKm = null,
        public ?int $feeComponentId = null,
    ) {}
}
