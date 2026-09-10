<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\DataObjects;

use Carbon\CarbonInterface;

/**
 * `hourlyRateMinor` is the staff member's own recorded rate, supplied
 * by the caller — `Modules\People` doesn't carry a per-staff hourly
 * rate column, so this module has nothing of its own to read it from.
 * Leave it `null` to fall back to
 * `maintenance.default_trade_hourly_rate_minor` (BR-OPS-02-006).
 */
final readonly class RecordWorkOrderLabourData
{
    public function __construct(
        public int $workOrderId,
        public int $staffId,
        public CarbonInterface $workDate,
        public float $hours,
        public int $recordedByUserId,
        public ?int $hourlyRateMinor = null,
        public ?string $notes = null,
    ) {}
}
