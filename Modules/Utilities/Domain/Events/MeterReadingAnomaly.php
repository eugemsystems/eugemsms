<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Events;

use Modules\Utilities\Models\MeterReading;

final class MeterReadingAnomaly
{
    public function __construct(
        public readonly MeterReading $reading,
        public readonly string $reason,
    ) {}
}
