<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\BehaviourRecord;

final class SafeguardingTriggerDetected
{
    public function __construct(
        public readonly BehaviourRecord $record,
    ) {}
}
