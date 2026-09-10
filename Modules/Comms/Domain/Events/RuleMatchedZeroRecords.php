<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

use Modules\Comms\Models\AutomationRule;

/**
 * Book I COM-02 §4/(AC-COM-02-007). Three consecutive scheduled runs
 * with zero matches — likely a broken condition, not genuinely zero
 * eligible records forever.
 */
final class RuleMatchedZeroRecords
{
    public function __construct(
        public readonly AutomationRule $rule,
        public readonly int $consecutiveZeroMatchRuns,
    ) {}
}
