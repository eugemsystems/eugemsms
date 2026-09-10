<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\DataObjects\AutomationScanRecord;
use Modules\Comms\Domain\DataObjects\ScanRuleResult;
use Modules\Comms\Domain\Registry\AutomationEntityRegistry;
use Modules\Comms\Domain\Support\ConditionEvaluator;
use Modules\Comms\Domain\Support\VariantPicker;
use Modules\Comms\Models\AutomationRule;
use Modules\Comms\Models\RuleExecution;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;

/**
 * ACT-EvaluateScanRule (Book I COM-02 §3 ⭐/BR-COM-02-002/005/006/012
 * (AC-COM-02-001/002/004/006)). The single engine behind both a real
 * scan (`RunScanRuleAction`) and preview mode (`PreviewAutomationRuleAction`,
 * `$dryRun = true`) — preview never dispatches and never writes a
 * `rule_executions` row, matching AC-COM-02-004 literally.
 *
 * Throttling (BR-COM-02-005) is ON TOP OF `CORE-09`'s own dedup: this
 * check is keyed by `(rule_id, subject_type, subject_id)` — the
 * natural, always-correct throttle scope, since a scan entity's
 * `subject_id` already IS the record `throttle_key` names in every
 * real registered entity (e.g. `invoice.id` for the `invoice` entity).
 * `CORE-09`'s own dedup (a different, notification-key-scoped check)
 * still runs independently inside `DispatchNotificationAction`.
 */
final class EvaluateScanRuleAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly ConditionEvaluator $evaluator,
        private readonly VariantPicker $variantPicker,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(AutomationRule $rule, bool $dryRun = false): ScanRuleResult
    {
        $entity = $rule->scan_entity !== null ? AutomationEntityRegistry::get($rule->scan_entity) : null;

        if ($entity === null) {
            return new ScanRuleResult(0, 0, 0);
        }

        $records = ($entity->scanner)($rule->school_id);
        $conditions = $rule->conditions()->get();
        $variants = $rule->variants()->get();

        $matched = 0;
        $dispatched = 0;
        $previewMatches = [];

        foreach ($records as $record) {
            if (! $this->evaluator->evaluate($conditions, $record->fields)) {
                if (! $dryRun) {
                    $this->logExecution($rule, $record, matched: false, skipReason: 'condition_not_met');
                }

                continue;
            }

            $matched++;

            if ($dryRun) {
                $previewMatches[] = ['subject_type' => $record->subjectType, 'subject_id' => $record->subjectId, 'context' => $record->context];

                continue;
            }

            if ($this->wasRecentlySent($rule, $record)) {
                $this->logExecution($rule, $record, matched: true, skipReason: 'throttled');

                continue;
            }

            $variant = $this->variantPicker->pick($variants, $rule->id, $record->subjectId);
            $key = $variant->template_key ?? $rule->template_key_override ?? $rule->notification_key;

            $notification = $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $record->schoolId,
                notificationKey: $key,
                recipientType: $record->recipientType,
                addresses: $record->addresses,
                context: $record->context,
                recipientId: $record->recipientId,
                channel: $rule->channel_override[0] ?? null,
                relatedType: $record->subjectType,
                relatedId: $record->subjectId,
            ));

            $this->logExecution($rule, $record, matched: true, skipReason: null, notificationId: $notification->id, variantKey: $variant?->variant_key);
            $dispatched++;

            $variant?->increment('sent_count');
        }

        return new ScanRuleResult($records->count(), $matched, $dispatched, $previewMatches);
    }

    private function wasRecentlySent(AutomationRule $rule, AutomationScanRecord $record): bool
    {
        if ($rule->throttle_key === null || $rule->throttle_window_hours === null) {
            return false;
        }

        return RuleExecution::where('rule_id', $rule->id)
            ->where('subject_type', $record->subjectType)
            ->where('subject_id', $record->subjectId)
            ->where('matched', true)
            ->whereNull('skip_reason')
            ->where('executed_at', '>=', Carbon::now()->subHours($rule->throttle_window_hours))
            ->exists();
    }

    private function logExecution(AutomationRule $rule, AutomationScanRecord $record, bool $matched, ?string $skipReason, ?int $notificationId = null, ?string $variantKey = null): void
    {
        RuleExecution::create([
            'school_id' => $rule->school_id,
            'rule_id' => $rule->id,
            'trigger_source' => 'scan',
            'subject_type' => $record->subjectType,
            'subject_id' => $record->subjectId,
            'matched' => $matched,
            'skip_reason' => $skipReason,
            'notification_id' => $notificationId,
            'variant_key' => $variantKey,
            'executed_at' => Carbon::now(),
        ]);
    }
}
