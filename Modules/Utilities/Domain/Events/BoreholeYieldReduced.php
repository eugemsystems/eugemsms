<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Events;

use Modules\Utilities\Models\WaterSource;

final class BoreholeYieldReduced
{
    public function __construct(
        public readonly WaterSource $source,
        public readonly float $yieldPercent,
    ) {}
}
