<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Automation;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\DeactivateAutomationRuleAction;
use Modules\Comms\Models\AutomationRule;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Automation\Index` (Book I COM-02 §5, `automation.view`) — the
 * rule library. Deactivation is offered inline (BR-COM-02-011: stops
 * future dispatch at once); *activation* deliberately is not — it needs
 * the cost-estimate review on the rule's own screen (BR-COM-02-008).
 */
#[Title('Automation rules')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $triggerFilter = '';

    public string $activeFilter = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('automation.view');
    }

    public function deactivate(int $ruleId): void
    {
        $this->authorizePermission('automation.manage');

        $rule = AutomationRule::where('school_id', $this->school->id)->findOrFail($ruleId);
        app(DeactivateAutomationRuleAction::class)->execute($rule->id);

        $this->toast(__('Rule deactivated. Messages already sent are not recalled.'));
    }

    public function render(): View
    {
        $rules = AutomationRule::where('school_id', $this->school->id)
            ->when($this->triggerFilter !== '', fn ($query) => $query->where('trigger_type', $this->triggerFilter))
            ->when($this->activeFilter !== '', fn ($query) => $query->where('is_active', $this->activeFilter === 'active'))
            ->orderBy('name')
            ->get()
            ->map(fn (AutomationRule $rule): array => [
                'rule' => $rule,
                'cost' => $rule->estimated_monthly_cost_minor !== null
                    ? Money::of($rule->estimated_monthly_cost_minor, Currency::tryFrom((string) $rule->estimated_monthly_currency) ?? Currency::from($this->school->base_currency))->format()
                    : null,
            ]);

        return view('comms::automation.index', [
            'rules' => $rules,
            'canManage' => app(PermissionScopeResolver::class)->has(auth()->user(), 'automation.manage', PermissionScope::Own),
        ]);
    }
}
