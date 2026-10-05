<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Automation;

use App\Concerns\Toasts;
use Cron\CronExpression;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\ActivateAutomationRuleAction;
use Modules\Comms\Domain\Actions\CreateAutomationRuleAction;
use Modules\Comms\Domain\Actions\DeactivateAutomationRuleAction;
use Modules\Comms\Domain\Actions\EstimateAutomationRuleCostAction;
use Modules\Comms\Domain\Actions\PreviewAutomationRuleAction;
use Modules\Comms\Domain\DataObjects\CreateAutomationRuleData;
use Modules\Comms\Domain\Exceptions\CostEstimateNotReviewedException;
use Modules\Comms\Domain\Exceptions\InvalidRuleEventException;
use Modules\Comms\Domain\Exceptions\InvalidRuleFieldException;
use Modules\Comms\Domain\Registry\AutomationEntityRegistry;
use Modules\Comms\Domain\Registry\AutomationEventRegistry;
use Modules\Comms\Models\AutomationRule;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Automation\Builder` (Book I COM-02 §5 ⭐, `automation.manage`).
 * One screen, two modes. With no `{rule}` it is the builder: condition
 * groups (AND within a group, OR across groups — BR-COM-02-004) with the
 * field picker scoped to the chosen entity or event (BR-COM-02-003), and
 * a save that creates the rule INACTIVE. With a `{rule}` it is the
 * rule's own screen: read-only definition, preview (BR-COM-02-012, no
 * dispatch), cost estimate, and activate/deactivate (BR-COM-02-008 — the
 * Action refuses activation without a reviewed estimate, and this
 * screen only offers the button once one exists).
 *
 * The cost estimate and preview need a saved rule (the backend Actions
 * take a rule id), so the spec's "cost estimate before save" is
 * delivered as "before activation" — which is what BR-COM-02-008
 * actually requires. There is no edit path for a saved rule's
 * conditions in the backend; a wrong rule is deactivated and re-created.
 * The rule is looked up by ulid in `mount()`, after the school context
 * is set, rather than via route-model binding.
 */
#[Title('Automation rule')]
#[Layout('layouts.app')]
final class Builder extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** @var array<int, string> */
    public const array OPERATORS = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in', 'contains', 'is_null'];

    public ?AutomationRule $automationRule = null;

    public string $name = '';

    public string $notificationKey = '';

    public string $triggerType = 'scheduled_scan';

    public string $eventName = '';

    public string $scanEntity = '';

    public string $scheduleCron = '0 8 * * *';

    public string $throttleKey = '';

    public string $throttleWindowHours = '';

    public int $delayMinutes = 0;

    /**
     * @var array<int, array{group_id: int, field: string, operator: string, value: string}>
     */
    public array $conditions = [];

    /**
     * @var array<int, array{subject_type: string, subject_id: int, context: array<string, mixed>}>
     */
    public array $previewMatches = [];

    public ?int $previewScanned = null;

    public function mount(School $school, ?string $rule = null): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('automation.manage');

        if ($rule !== null) {
            $this->automationRule = AutomationRule::where('school_id', $school->id)->where('ulid', $rule)->firstOrFail();

            return;
        }

        $this->conditions = [['group_id' => 1, 'field' => '', 'operator' => 'eq', 'value' => '']];
    }

    public function addCondition(int $groupId = 1): void
    {
        $this->conditions[] = ['group_id' => max(1, $groupId), 'field' => '', 'operator' => 'eq', 'value' => ''];
    }

    public function addGroup(): void
    {
        $this->addCondition(max(array_column($this->conditions, 'group_id') ?: [0]) + 1);
    }

    public function removeCondition(int $index): void
    {
        unset($this->conditions[$index]);
        $this->conditions = array_values($this->conditions);
    }

    public function save(): void
    {
        $this->authorizePermission('automation.manage');
        abort_if($this->automationRule !== null, 403);

        $isScan = $this->triggerType === 'scheduled_scan';

        $this->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('automation_rules', 'name')->where('school_id', $this->school->id)],
            'notificationKey' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! NotificationKeyRegistry::has((string) $value)) {
                    $fail(__('That notification key is not registered.'));
                }
            }],
            'triggerType' => ['required', 'in:event,scheduled_scan'],
            'eventName' => [$isScan ? 'nullable' : 'required', 'string', 'max:80'],
            'scanEntity' => [$isScan ? 'required' : 'nullable', 'string', 'max:40'],
            'scheduleCron' => [$isScan ? 'required' : 'nullable', 'string', 'max:60', function (string $attribute, mixed $value, \Closure $fail) use ($isScan): void {
                if ($isScan && ! CronExpression::isValidExpression((string) $value)) {
                    $fail(__('Enter a valid cron expression, like 0 8 * * *.'));
                }
            }],
            'throttleKey' => ['nullable', 'string', 'max:120'],
            'throttleWindowHours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'delayMinutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'conditions' => ['array'],
            'conditions.*.group_id' => ['required', 'integer', 'min:1', 'max:20'],
            'conditions.*.field' => ['required', 'string', 'max:80'],
            'conditions.*.operator' => ['required', 'in:'.implode(',', self::OPERATORS)],
        ]);

        try {
            $created = app(CreateAutomationRuleAction::class)->execute(new CreateAutomationRuleData(
                schoolId: $this->school->id,
                name: $this->name,
                notificationKey: $this->notificationKey,
                triggerType: $this->triggerType,
                conditions: array_map(fn (array $condition): array => [
                    'group_id' => (int) $condition['group_id'],
                    'field' => $condition['field'],
                    'operator' => $condition['operator'],
                    'value' => $this->parseConditionValue($condition['operator'], $condition['value']),
                ], $this->conditions),
                createdByUserId: (int) auth()->id(),
                eventName: $isScan ? null : $this->eventName,
                scheduleCron: $isScan ? $this->scheduleCron : null,
                scanEntity: $isScan ? $this->scanEntity : null,
                delayMinutes: $this->delayMinutes,
                throttleKey: $this->throttleKey !== '' ? $this->throttleKey : null,
                throttleWindowHours: $this->throttleWindowHours !== '' ? (int) $this->throttleWindowHours : null,
            ));
        } catch (InvalidRuleEventException|InvalidRuleFieldException $exception) {
            $this->addError('conditions', $exception->getMessage());

            return;
        }

        $this->toast(__('Rule saved as inactive. Preview it and review its cost estimate to activate.'));
        $this->redirectRoute('comms.automation.builder', [$this->school, $created->ulid], navigate: true);
    }

    public function preview(): void
    {
        $this->authorizePermission('automation.manage');
        abort_if($this->automationRule === null, 404);

        $result = app(PreviewAutomationRuleAction::class)->execute($this->automationRule->id);

        $this->previewScanned = $result->recordsScanned;
        $this->previewMatches = array_slice($result->previewMatches, 0, 50);
    }

    public function estimateCost(): void
    {
        $this->authorizePermission('automation.manage');
        abort_if($this->automationRule === null, 404);

        $this->automationRule = app(EstimateAutomationRuleCostAction::class)->execute($this->automationRule->id);
        $this->toast(__('Cost estimate updated — review it before activating.'));
    }

    public function activate(): void
    {
        $this->authorizePermission('automation.manage');
        abort_if($this->automationRule === null, 404);

        try {
            $this->automationRule = app(ActivateAutomationRuleAction::class)->execute($this->automationRule->id);
        } catch (CostEstimateNotReviewedException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Rule activated.'));
    }

    public function deactivate(): void
    {
        $this->authorizePermission('automation.manage');
        abort_if($this->automationRule === null, 404);

        $this->automationRule = app(DeactivateAutomationRuleAction::class)->execute($this->automationRule->id);
        $this->toast(__('Rule deactivated. Messages already sent are not recalled.'));
    }

    public function render(): View
    {
        $allowedFields = $this->allowedFields();

        return view('comms::automation.builder', [
            'notificationKeys' => array_keys(NotificationKeyRegistry::all()),
            'eventNames' => array_keys(AutomationEventRegistry::all()),
            'entityKeys' => array_keys(AutomationEntityRegistry::all()),
            'allowedFields' => $allowedFields,
            'operators' => self::OPERATORS,
            'ruleConditions' => $this->automationRule?->conditions()->orderBy('group_id')->orderBy('id')->get() ?? collect(),
            'estimate' => $this->automationRule?->estimated_monthly_cost_minor !== null
                ? Money::of((int) $this->automationRule->estimated_monthly_cost_minor, Currency::tryFrom((string) $this->automationRule->estimated_monthly_currency) ?? Currency::from($this->school->base_currency))->format()
                : null,
        ]);
    }

    /**
     * The fields the chosen event or entity exposes for automation
     * (BR-COM-02-003); none until one is chosen.
     *
     * @return array<int, string>
     */
    private function allowedFields(): array
    {
        $definition = $this->triggerType === 'event'
            ? AutomationEventRegistry::get($this->eventName)
            : AutomationEntityRegistry::get($this->scanEntity);

        return $definition === null ? [] : $definition->allowedFields;
    }

    /**
     * Operators decide the value's shape: lists for in/not_in, nothing
     * for is_null, otherwise a bool/number when it looks like one.
     */
    private function parseConditionValue(string $operator, string $raw): mixed
    {
        $raw = trim($raw);

        return match (true) {
            $operator === 'is_null' => null,
            in_array($operator, ['in', 'not_in'], true) => array_values(array_filter(array_map(fn (string $item): mixed => $this->scalar(trim($item)), explode(',', $raw)), fn (mixed $item): bool => $item !== '')),
            default => $this->scalar($raw),
        };
    }

    private function scalar(string $raw): mixed
    {
        return match (true) {
            strtolower($raw) === 'true' => true,
            strtolower($raw) === 'false' => false,
            is_numeric($raw) => str_contains($raw, '.') ? (float) $raw : (int) $raw,
            default => $raw,
        };
    }
}
