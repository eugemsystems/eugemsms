<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

use Modules\Comms\Models\AutomationRule;

/**
 * Book I COM-02 §4/BR-COM-02-011. Stops FUTURE dispatch only — never
 * retracts or recalls messages already sent.
 */
final class RuleDeactivated
{
    public function __construct(
        public readonly AutomationRule $rule,
    ) {}
}
