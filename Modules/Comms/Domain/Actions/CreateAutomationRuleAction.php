<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Domain\DataObjects\CreateAutomationRuleData;
use Modules\Comms\Domain\Exceptions\InvalidRuleEventException;
use Modules\Comms\Domain\Exceptions\InvalidRuleFieldException;
use Modules\Comms\Domain\Registry\AutomationEntityRegistry;
use Modules\Comms\Domain\Registry\AutomationEventRegistry;
use Modules\Comms\Models\AutomationRule;
use Modules\Comms\Models\RuleCondition;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateAutomationRule (Book I COM-02 §4 ⭐/BR-COM-02-001/003
 * (AC-COM-02-003)). Refuses at save time — never at scan/dispatch
 * time — both an event no module publishes and a field the owning
 * entity/event hasn't exposed for automation.
 */
final class CreateAutomationRuleAction extends Action
{
    public function execute(CreateAutomationRuleData $data): AutomationRule
    {
        $allowedFields = $this->allowedFieldsFor($data);

        foreach ($data->conditions as $condition) {
            if (! in_array($condition['field'], $allowedFields, true)) {
                $entityOrEvent = $data->scanEntity ?? $data->eventName ?? 'unknown';

                throw InvalidRuleFieldException::forField($entityOrEvent, $condition['field']);
            }
        }

        return $this->transaction(function () use ($data): AutomationRule {
            $rule = AutomationRule::create([
                'school_id' => $data->schoolId,
                'name' => $data->name,
                'notification_key' => $data->notificationKey,
                'trigger_type' => $data->triggerType,
                'event_name' => $data->eventName,
                'schedule_cron' => $data->scheduleCron,
                'scan_entity' => $data->scanEntity,
                'audience_override' => $data->audienceOverride,
                'channel_override' => $data->channelOverride,
                'template_key_override' => $data->templateKeyOverride,
                'delay_minutes' => $data->delayMinutes,
                'throttle_key' => $data->throttleKey,
                'throttle_window_hours' => $data->throttleWindowHours,
                'is_active' => false,
                'created_by' => $data->createdByUserId,
            ]);

            foreach ($data->conditions as $condition) {
                RuleCondition::create([
                    'rule_id' => $rule->id,
                    'group_id' => $condition['group_id'],
                    'group_logic' => 'AND',
                    'field' => $condition['field'],
                    'operator' => $condition['operator'],
                    'value' => $condition['value'],
                ]);
            }

            return $rule;
        });
    }

    /**
     * @return array<int, string>
     */
    private function allowedFieldsFor(CreateAutomationRuleData $data): array
    {
        if ($data->triggerType === 'event') {
            if ($data->eventName === null || ! AutomationEventRegistry::isRegistered($data->eventName)) {
                throw InvalidRuleEventException::forEvent($data->eventName ?? '');
            }

            return AutomationEventRegistry::get($data->eventName)->allowedFields;
        }

        $entity = $data->scanEntity !== null ? AutomationEntityRegistry::get($data->scanEntity) : null;

        return $entity !== null ? $entity->allowedFields : [];
    }
}
