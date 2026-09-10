<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Events;

use Modules\Farm\Models\CropCycle;

final class CropCyclePlanted
{
    public function __construct(
        public readonly CropCycle $cropCycle,
    ) {}
}
