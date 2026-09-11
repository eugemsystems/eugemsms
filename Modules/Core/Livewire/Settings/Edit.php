<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Settings;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Settings\ResetSettingToInheritedAction;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\DataObjects\Settings\ResetSettingData;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\SettingDefinition;
use Modules\Core\Models\SettingValue;

/**
 * `Core\Settings\Edit` (Book A CORE-04 §5). Writes exactly one setting
 * at School scope for this school via `SetSettingValueAction` — the
 * only scope this admin screen offers, since every other CORE-0X screen
 * in this app operates per-school. A setting whose `lowest_scope` is
 * narrower than School (term/academic_year/section/user) cannot be set
 * here at all — `$schoolScopeAllowed` mirrors the action's own guard so
 * the form says so instead of submitting and failing.
 */
#[Title('Edit setting')]
#[Layout('layouts.app')]
final class Edit extends Component
{
    use InteractsWithSchool;
    use Toasts;

    public SettingDefinition $definition;

    public bool $schoolScopeAllowed = true;

    public bool $hasOverride = false;

    public string $value = '';

    public bool $boolValue = false;

    public function mount(School $school, string $key): void
    {
        $this->loadSchool($school);

        $this->definition = SettingDefinition::where('key', $key)->firstOr(fn () => abort(404));

        $lowestScope = SettingScope::from($this->definition->lowest_scope);
        $this->schoolScopeAllowed = ! (SettingScope::School->isAtLeastAsGeneralAs($lowestScope) && $lowestScope !== SettingScope::School);

        $existing = SettingValue::where('setting_key', $this->definition->key)
            ->where('scope_type', SettingScope::School)
            ->where('scope_id', $school->id)
            ->first();

        $this->hasOverride = $existing !== null;
        $this->value = $existing !== null ? ($existing->value ?? '') : '';
        $this->boolValue = filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
    }

    public function save(): void
    {
        if (! $this->schoolScopeAllowed) {
            $this->toast(__('This setting can only be overridden at a narrower scope than school.'), 'danger');

            return;
        }

        $value = $this->definition->data_type === 'bool' ? $this->boolValue : ($this->value === '' ? null : $this->value);

        try {
            app(SetSettingValueAction::class)->execute(new SetSettingValueData(
                key: $this->definition->key,
                scopeType: SettingScope::School,
                scopeId: $this->school->id,
                value: $value,
                setByUserId: (int) Auth::id(),
                ipAddress: request()->ip(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->hasOverride = true;

        $this->toast(__('Setting updated.'));
    }

    public function resetToInherited(): void
    {
        app(ResetSettingToInheritedAction::class)->execute(new ResetSettingData(
            key: $this->definition->key,
            scopeType: SettingScope::School,
            scopeId: $this->school->id,
            performedByUserId: (int) Auth::id(),
            ipAddress: request()->ip(),
        ));

        $this->hasOverride = false;
        $this->value = '';
        $this->boolValue = false;

        $this->toast(__('Reset — this school now inherits the broader default.'));
    }

    public function render(): View
    {
        $chain = new ScopeChain(schoolId: $this->school->id, tenantId: $this->school->tenant_id);
        $effectiveValue = app(SettingResolver::class)->get($this->definition->key, $chain);

        return view('core::settings.edit', [
            'effectiveValue' => $effectiveValue,
        ]);
    }
}
