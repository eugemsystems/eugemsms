<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

use Modules\Comms\Models\AutomationRule;

final class RuleActivated
{
    public function __construct(
        public readonly AutomationRule $rule,
    ) {}
}
