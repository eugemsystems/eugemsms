<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Domain\DataObjects\ScanRuleResult;
use Modules\Comms\Models\AutomationRule;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-PreviewAutomationRule (Book I COM-02 §4/BR-COM-02-012
 * (AC-COM-02-004)). Dry run against current data — no dispatch, no
 * `rule_executions` write, exactly who would receive what.
 */
final class PreviewAutomationRuleAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly EvaluateScanRuleAction $evaluateScanRule,
    ) {}

    public function execute(int $ruleId): ScanRuleResult
    {
        $rule = AutomationRule::findOrFail($ruleId);

        return $this->evaluateScanRule->execute($rule, dryRun: true);
    }
}
