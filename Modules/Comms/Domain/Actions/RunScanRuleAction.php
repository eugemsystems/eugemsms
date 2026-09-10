<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\Events\RuleMatchedZeroRecords;
use Modules\Comms\Domain\Events\ScanCompleted;
use Modules\Comms\Models\AutomationRule;
use Modules\Comms\Models\ScanRun;
use Modules\Core\Domain\Actions\Action;
use Throwable;

/**
 * ACT-RunScanRule (Book I COM-02 §4/BR-COM-02-002 (AC-COM-02-007)).
 * The scheduled entry point for a `scheduled_scan` rule — records a
 * `scan_runs` row whatever the outcome, and fires
 * `RuleMatchedZeroRecords` the moment three CONSECUTIVE runs (this
 * one included) all matched nothing, since that's far more likely a
 * broken condition than a genuinely empty population forever.
 */
final class RunScanRuleAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly EvaluateScanRuleAction $evaluateScanRule,
    ) {}

    public function execute(int $ruleId): ScanRun
    {
        $rule = AutomationRule::findOrFail($ruleId);
        $startedAt = Carbon::now();

        try {
            $result = $this->evaluateScanRule->execute($rule, dryRun: false);

            $scanRun = ScanRun::create([
                'school_id' => $rule->school_id,
                'rule_id' => $rule->id,
                'ran_at' => $startedAt,
                'records_scanned' => $result->recordsScanned,
                'records_matched' => $result->recordsMatched,
                'notifications_dispatched' => $result->notificationsDispatched,
                'duration_ms' => $startedAt->diffInMilliseconds(Carbon::now()),
                'status' => 'completed',
            ]);
        } catch (Throwable $exception) {
            $scanRun = ScanRun::create([
                'school_id' => $rule->school_id,
                'rule_id' => $rule->id,
                'ran_at' => $startedAt,
                'duration_ms' => $startedAt->diffInMilliseconds(Carbon::now()),
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            event(new ScanCompleted($scanRun));

            throw $exception;
        }

        event(new ScanCompleted($scanRun));
        $this->checkConsecutiveZeroMatches($rule);

        return $scanRun;
    }

    private function checkConsecutiveZeroMatches(AutomationRule $rule): void
    {
        $lastThree = ScanRun::where('rule_id', $rule->id)
            ->where('status', 'completed')
            ->orderByDesc('ran_at')
            ->limit(3)
            ->get();

        if ($lastThree->count() === 3 && $lastThree->every(fn (ScanRun $run): bool => $run->records_matched === 0)) {
            event(new RuleMatchedZeroRecords($rule, 3));
        }
    }
}
