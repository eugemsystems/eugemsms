<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Approvals;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Approvals\CreateApprovalChainAction;
use Modules\Core\Domain\DataObjects\Approvals\ApprovalStepData;
use Modules\Core\Domain\DataObjects\Approvals\CreateApprovalChainData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;

/**
 * `Core\Approvals\ChainBuilder` (Book A CORE-07 §5, `core.approval.configure`)
 * — create a chain with its steps in one submit (`CreateApprovalChainAction`
 * writes the chain and every step in a single transaction). "Visual
 * step editor with condition rules and preview" (the spec's own
 * description) is simplified to a repeatable step form plus raw JSON
 * for `condition_rules` at both chain and step level, rather than a
 * graphical rule builder or a live preview against sample data —
 * matching the same honest simplification made for the template
 * editor's live preview in CORE-06.
 */
#[Title('New approval chain')]
#[Layout('layouts.app')]
final class ChainBuilder extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $approvableType = '';

    public string $name = '';

    public string $description = '';

    public bool $isDefault = false;

    public int $priority = 0;

    public string $conditionRulesJson = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $steps = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.approval.configure');

        $this->addStep();
    }

    public function addStep(): void
    {
        $this->steps[] = [
            'name' => '',
            'approverType' => 'role',
            'approverRoleId' => '',
            'approverUserId' => '',
            'dynamicResolver' => '',
            'mode' => 'sequential',
            'requiredApprovals' => 1,
            'escalateAfterHours' => '',
            'escalateToRoleId' => '',
            'canReject' => true,
            'canReturn' => true,
            'requiresComment' => false,
            'conditionRulesJson' => '',
        ];
    }

    public function removeStep(int $index): void
    {
        if (count($this->steps) <= 1) {
            return;
        }

        unset($this->steps[$index]);
        $this->steps = array_values($this->steps);
    }

    public function save(): void
    {
        $this->validate([
            'approvableType' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', 'integer', 'min:0'],
            'conditionRulesJson' => ['nullable', 'string'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.name' => ['required', 'string', 'max:120'],
            'steps.*.approverType' => ['required', Rule::in(['user', 'role', 'dynamic'])],
            'steps.*.mode' => ['required', Rule::in(['sequential', 'parallel_all', 'parallel_any'])],
            'steps.*.requiredApprovals' => ['required', 'integer', 'min:1'],
        ]);

        $conditionRules = $this->decodeJsonRules($this->conditionRulesJson, 'conditionRulesJson');

        if ($conditionRules === false) {
            return;
        }

        $stepData = [];

        foreach ($this->steps as $index => $step) {
            $stepRules = $this->decodeJsonRules((string) ($step['conditionRulesJson'] ?? ''), "steps.{$index}.conditionRulesJson");

            if ($stepRules === false) {
                return;
            }

            $stepData[] = new ApprovalStepData(
                stepNumber: $index + 1,
                name: $step['name'],
                approverType: $step['approverType'],
                mode: $step['mode'],
                approverRoleId: $step['approverType'] === 'role' && $step['approverRoleId'] !== '' ? (int) $step['approverRoleId'] : null,
                approverUserId: $step['approverType'] === 'user' && $step['approverUserId'] !== '' ? (int) $step['approverUserId'] : null,
                dynamicResolver: $step['approverType'] === 'dynamic' && $step['dynamicResolver'] !== '' ? $step['dynamicResolver'] : null,
                requiredApprovals: (int) $step['requiredApprovals'],
                conditionRules: $stepRules,
                escalateAfterHours: $step['escalateAfterHours'] !== '' ? (int) $step['escalateAfterHours'] : null,
                escalateToRoleId: $step['escalateToRoleId'] !== '' ? (int) $step['escalateToRoleId'] : null,
                canReject: (bool) $step['canReject'],
                canReturn: (bool) $step['canReturn'],
                requiresComment: (bool) $step['requiresComment'],
            );
        }

        try {
            app(CreateApprovalChainAction::class)->execute(new CreateApprovalChainData(
                schoolId: $this->school->id,
                approvableType: $this->approvableType,
                name: $this->name,
                steps: $stepData,
                description: $this->description !== '' ? $this->description : null,
                conditionRules: $conditionRules,
                isDefault: $this->isDefault,
                priority: $this->priority,
                createdByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Approval chain created.'));

        $this->redirectRoute('approvals.chains', ['school' => $this->school], navigate: true);
    }

    /**
     * @return array<int, array{field: string, operator: string, value: mixed}>|null|false false means invalid JSON — the error is already added to the bag
     */
    private function decodeJsonRules(string $json, string $field): array|null|false
    {
        if (trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            $this->addError($field, __('This must be valid JSON — an array of {field, operator, value} rules.'));

            return false;
        }

        return $decoded;
    }

    public function render(): View
    {
        return view('core::approvals.chain-builder', [
            'roles' => Role::query()->where(fn ($q) => $q->where('school_id', $this->school->id)->orWhereNull('school_id'))->orderBy('display_name')->get(),
        ]);
    }
}
