<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Automation;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\AddRuleTemplateVariantAction;
use Modules\Comms\Domain\DataObjects\AddRuleTemplateVariantData;
use Modules\Comms\Models\AutomationRule;
use Modules\Comms\Models\RuleTemplateVariant;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Automation\Variants` (Book I COM-02 §5, `automation.view`;
 * adding one needs `automation.manage`). Per-variant sent/opened/
 * response performance (BR-COM-02-010) so the losing variant can be
 * retired. `rule_template_variants` has no `school_id` of its own, so
 * every read goes through the school's own rule ids. There is no
 * remove/edit-weight Action in the backend, so none is offered; the
 * running weight total is shown and capped at 100.
 */
#[Title('A/B performance')]
#[Layout('layouts.app')]
final class Variants extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $ruleId = null;

    public string $variantKey = '';

    public string $templateKey = '';

    public int $weightPercent = 50;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('automation.view');
    }

    public function addVariant(): void
    {
        $this->authorizePermission('automation.manage');

        $this->validate([
            'ruleId' => ['required', 'integer'],
            'variantKey' => ['required', 'string', 'max:20', 'alpha_dash'],
            'templateKey' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! NotificationKeyRegistry::has((string) $value)) {
                    $fail(__('That template key is not a registered notification key.'));
                }
            }],
            'weightPercent' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $rule = AutomationRule::where('school_id', $this->school->id)->findOrFail($this->ruleId);
        $existing = RuleTemplateVariant::where('rule_id', $rule->id)->get();

        if ($existing->contains('variant_key', $this->variantKey)) {
            $this->addError('variantKey', __('That variant key is already used on this rule.'));

            return;
        }

        if ($existing->sum('weight_percent') + $this->weightPercent > 100) {
            $this->addError('weightPercent', __('Variant weights on a rule cannot total more than 100%.'));

            return;
        }

        app(AddRuleTemplateVariantAction::class)->execute(new AddRuleTemplateVariantData(
            ruleId: $rule->id,
            variantKey: $this->variantKey,
            templateKey: $this->templateKey,
            weightPercent: $this->weightPercent,
        ));

        $this->reset(['variantKey', 'templateKey']);
        $this->toast(__('Variant added.'));
    }

    public function render(): View
    {
        $rules = AutomationRule::where('school_id', $this->school->id)->orderBy('name')->get(['id', 'name']);

        return view('comms::automation.variants', [
            'rules' => $rules,
            'variants' => RuleTemplateVariant::whereIn('rule_id', $rules->pluck('id'))
                ->when($this->ruleId !== null, fn ($query) => $query->where('rule_id', $this->ruleId))
                ->orderBy('rule_id')->orderBy('variant_key')->get(),
            'ruleNames' => $rules->pluck('name', 'id'),
            'notificationKeys' => array_keys(NotificationKeyRegistry::all()),
            'canManage' => app(PermissionScopeResolver::class)->has(auth()->user(), 'automation.manage', PermissionScope::Own),
        ]);
    }
}
