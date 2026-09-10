<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Welfare\Domain\DataObjects\CreateTriggerRuleData;
use Modules\Welfare\Models\BehaviourTriggerRule;

/**
 * ACT-CreateTriggerRule (Book G BRD-07 §2/BR-BRD-07-003). Enabling
 * `isAutomatic` is itself a logged setting change — this action refuses
 * it unless `behaviour.automatic_sanctioning_enabled` is already on,
 * so a rule can never quietly auto-sanction ahead of that school-wide
 * decision being made explicitly.
 */
final class CreateTriggerRuleAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(CreateTriggerRuleData $data): BehaviourTriggerRule
    {
        if ($data->isAutomatic) {
            $scope = new ScopeChain(schoolId: $data->schoolId);

            if (! (bool) $this->settings->get('behaviour.automatic_sanctioning_enabled', $scope)) {
                throw new InvalidStateTransitionException(
                    'behaviour.automatic_sanctioning_enabled must be turned on before a rule can be created as automatic.',
                    ['school_id' => $data->schoolId],
                );
            }
        }

        return $this->transaction(fn (): BehaviourTriggerRule => BehaviourTriggerRule::create([
            'school_id' => $data->schoolId,
            'name' => $data->name,
            'trigger_type' => $data->triggerType,
            'demerit_threshold' => $data->demeritThreshold,
            'window_days' => $data->windowDays,
            'category_id' => $data->categoryId,
            'repeat_count' => $data->repeatCount,
            'suggested_sanction_id' => $data->suggestedSanctionId,
            'notify_role_id' => $data->notifyRoleId,
            'is_automatic' => $data->isAutomatic,
            'is_active' => true,
        ]));
    }
}
