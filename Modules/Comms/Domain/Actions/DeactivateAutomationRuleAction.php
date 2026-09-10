<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Domain\Events\RuleDeactivated;
use Modules\Comms\Models\AutomationRule;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-DeactivateAutomationRule (Book I COM-02 §4/BR-COM-02-011).
 * Stops future dispatch immediately; already-sent messages stand.
 */
final class DeactivateAutomationRuleAction extends Action
{
    public function execute(int $ruleId): AutomationRule
    {
        return $this->transaction(function () use ($ruleId): AutomationRule {
            $rule = AutomationRule::findOrFail($ruleId);
            $rule->update(['is_active' => false]);
            event(new RuleDeactivated($rule));

            return $rule;
        });
    }
}
