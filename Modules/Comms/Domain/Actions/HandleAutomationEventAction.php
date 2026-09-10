<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\Registry\AutomationEventRegistry;
use Modules\Comms\Domain\Support\ConditionEvaluator;
use Modules\Comms\Models\AutomationRule;
use Modules\Comms\Models\RuleExecution;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;

/**
 * ACT-HandleAutomationEvent (Book I COM-02 §3/BR-COM-02-001/005/006).
 * The event-triggered counterpart to `EvaluateScanRuleAction` — same
 * engine shape (evaluate, throttle, dispatch, log), fired the moment
 * a REGISTERED domain event actually happens rather than on a schedule.
 * A real Laravel event listener calling this for each registered
 * event is per-owning-module wiring — see `AutomationEventRegistry`'s
 * own documented scope boundary for why only a small starting set is
 * wired in this pass.
 */
final class HandleAutomationEventAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly ConditionEvaluator $evaluator,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(string $eventName, object $event): int
    {
        $definition = AutomationEventRegistry::get($eventName);

        if ($definition === null) {
            return 0;
        }

        $record = ($definition->extractor)($event);

        $rules = AutomationRule::where('school_id', $record->schoolId)
            ->where('trigger_type', 'event')
            ->where('event_name', $eventName)
            ->where('is_active', true)
            ->get();

        $dispatched = 0;

        foreach ($rules as $rule) {
            $conditions = $rule->conditions()->get();

            if (! $this->evaluator->evaluate($conditions, $record->fields)) {
                $this->logExecution($rule, $eventName, $record->subjectType, $record->subjectId, matched: false, skipReason: 'condition_not_met');

                continue;
            }

            if ($this->wasRecentlySent($rule, $record->subjectType, $record->subjectId)) {
                $this->logExecution($rule, $eventName, $record->subjectType, $record->subjectId, matched: true, skipReason: 'throttled');

                continue;
            }

            $notification = $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $record->schoolId,
                notificationKey: $rule->template_key_override ?? $rule->notification_key,
                recipientType: $record->recipientType,
                addresses: $record->addresses,
                context: $record->context,
                recipientId: $record->recipientId,
                channel: $rule->channel_override[0] ?? null,
                relatedType: $record->subjectType,
                relatedId: $record->subjectId,
            ));

            $this->logExecution($rule, $eventName, $record->subjectType, $record->subjectId, matched: true, skipReason: null, notificationId: $notification->id);
            $dispatched++;
        }

        return $dispatched;
    }

    private function wasRecentlySent(AutomationRule $rule, string $subjectType, int $subjectId): bool
    {
        if ($rule->throttle_key === null || $rule->throttle_window_hours === null) {
            return false;
        }

        return RuleExecution::where('rule_id', $rule->id)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('matched', true)
            ->whereNull('skip_reason')
            ->where('executed_at', '>=', Carbon::now()->subHours($rule->throttle_window_hours))
            ->exists();
    }

    private function logExecution(AutomationRule $rule, string $triggerSource, string $subjectType, int $subjectId, bool $matched, ?string $skipReason, ?int $notificationId = null): void
    {
        RuleExecution::create([
            'school_id' => $rule->school_id,
            'rule_id' => $rule->id,
            'trigger_source' => $triggerSource,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'matched' => $matched,
            'skip_reason' => $skipReason,
            'notification_id' => $notificationId,
            'executed_at' => Carbon::now(),
        ]);
    }
}
