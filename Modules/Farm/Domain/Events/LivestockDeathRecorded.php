<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Events;

use Modules\Farm\Models\LivestockEvent;

final class LivestockDeathRecorded
{
    public function __construct(
        public readonly LivestockEvent $event,
    ) {}
}
