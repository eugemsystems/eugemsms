<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Events;

use Modules\Utilities\Models\WaterReading;

final class WaterStorageLow
{
    public function __construct(
        public readonly WaterReading $reading,
    ) {}
}
