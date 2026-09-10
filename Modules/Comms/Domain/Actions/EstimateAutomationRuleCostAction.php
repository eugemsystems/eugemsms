<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Models\AutomationRule;
use Modules\Comms\Models\ProviderRateCard;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\School;

/**
 * ACT-EstimateAutomationRuleCost (Book I COM-02 §4 ⭐/BR-COM-02-008).
 * Runs a real preview (no dispatch) to get the actual current match
 * volume, then prices it against the school's own SMS rate card —
 * the same `provider_rate_cards` table COM-01's cost reconciliation
 * reads, so an estimate and an actual reconciled cost are never
 * computed two different ways.
 */
final class EstimateAutomationRuleCostAction extends Action
{
    public function __construct(
        private readonly PreviewAutomationRuleAction $preview,
    ) {}

    public function execute(int $ruleId): AutomationRule
    {
        $rule = AutomationRule::findOrFail($ruleId);
        $result = $this->preview->execute($ruleId);

        $ratePerMessageMinor = ProviderRateCard::where('destination_prefix', '263')
            ->where(fn ($q) => $q->whereNull('school_id')->orWhere('school_id', $rule->school_id))
            ->orderByDesc('effective_from')
            ->value('rate_per_segment_minor') ?? 2;

        $school = School::findOrFail($rule->school_id);

        return $this->transaction(function () use ($rule, $result, $ratePerMessageMinor, $school): AutomationRule {
            $rule->update([
                'estimated_monthly_cost_minor' => $result->recordsMatched * $ratePerMessageMinor,
                'estimated_monthly_currency' => $school->base_currency,
            ]);

            return $rule->fresh();
        });
    }
}
