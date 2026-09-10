<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Domain\Events\RuleActivated;
use Modules\Comms\Domain\Exceptions\CostEstimateNotReviewedException;
use Modules\Comms\Models\AutomationRule;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-ActivateAutomationRule (Book I COM-02 §4 ⭐/BR-COM-02-008
 * (AC-COM-02-005)). Refuses outright without a reviewed cost estimate
 * — `EstimateAutomationRuleCostAction` is what sets it.
 */
final class ActivateAutomationRuleAction extends Action
{
    public function execute(int $ruleId): AutomationRule
    {
        return $this->transaction(function () use ($ruleId): AutomationRule {
            $rule = AutomationRule::findOrFail($ruleId);

            if (! $rule->hasReviewedCostEstimate()) {
                throw CostEstimateNotReviewedException::forRule($rule->id);
            }

            $rule->update(['is_active' => true]);
            event(new RuleActivated($rule));

            return $rule;
        });
    }
}
