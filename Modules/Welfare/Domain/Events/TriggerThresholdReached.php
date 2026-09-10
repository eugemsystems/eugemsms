<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\BehaviourTriggerRule;

final class TriggerThresholdReached
{
    public function __construct(
        public readonly BehaviourTriggerRule $rule,
        public readonly int $studentId,
    ) {}
}
