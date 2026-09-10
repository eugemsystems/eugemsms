<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Events;

use Modules\Farm\Models\Harvest;

final class HarvestRecorded
{
    public function __construct(
        public readonly Harvest $harvest,
    ) {}
}
