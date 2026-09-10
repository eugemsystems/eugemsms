<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Events;

use Modules\Farm\Models\CropCycle;

final class CropCycleFailed
{
    public function __construct(
        public readonly CropCycle $cropCycle,
    ) {}
}
