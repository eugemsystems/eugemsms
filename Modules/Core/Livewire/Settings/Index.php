<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Settings;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
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
 * `Core\Settings\Index` (Book A CORE-04 §5). Every registered
 * `SettingDefinition` grouped into one module tab per distinct
 * `module_code` (sub-grouped by `group_key` within a tab), with the
 * value currently in effect for this school (walked via
 * `SettingResolver`, per BR-CORE-04-002) editable inline — no more
 * click-through to a separate edit screen. Replaces the former
 * `Settings\Edit` screen entirely; its resolution/guard logic lives on
 * here now (`schoolScopeAllowedFor()`, `SetSettingValueAction`,
 * `ResetSettingToInheritedAction`).
 *
 * Save behaviour is split by input type to minimise admin clicks
 * (user-requested redesign, 2026-09-11): a bool setting saves the
 * instant its switch is toggled (`saveBool()`), everything else is
 * deferred (`wire:model`, not `.live`) behind an explicit per-field save
 * button (`saveField()`) so typing doesn't fire a request per keystroke.
 */
#[Title('Settings')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use InteractsWithSchool;
    use Toasts;

    #[Url(as: 'module', history: true)]
    public string $activeModule = '';

    #[Url(as: 'q', history: true)]
    public string $search = '';

    /**
     * Pending (not-yet-saved) input values for non-bool settings, keyed
     * by `SettingDefinition::id` (never by `key` — setting keys contain
     * dots, e.g. `core.max_students`, which Livewire's `wire:model` would
     * otherwise parse as nested array access). Seeded lazily from the
     * resolved effective value the first time a definition is rendered,
     * then left alone across re-renders so an unsaved edit in one field
     * survives a Livewire round-trip triggered by another field's save.
     *
     * @var array<int, string>
     */
    public array $values = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function setActiveModule(string $moduleCode): void
    {
        $this->activeModule = $moduleCode;
        $this->resetErrorBag();
    }

    public function saveField(int $definitionId): void
    {
        $definition = SettingDefinition::findOrFail($definitionId);

        if (! $this->schoolScopeAllowedFor($definition)) {
            $this->toast(__('This setting can only be overridden at a narrower scope than school.'), 'danger');

            return;
        }

        $raw = $this->values[$definitionId] ?? '';
        $value = $raw === '' ? null : $raw;

        try {
            app(SetSettingValueAction::class)->execute(new SetSettingValueData(
                key: $definition->key,
                scopeType: SettingScope::School,
                scopeId: $this->school->id,
                value: $value,
                setByUserId: (int) Auth::id(),
                ipAddress: request()->ip(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        } catch (ValidationException $e) {
            // The action validates against a single generic 'value' field,
            // not one scoped per setting id — surfacing it via the normal
            // $errors bag would be ambiguous across many inline fields, so
            // it's shown as a toast instead, same as a DomainException.
            $this->toast($e->validator->errors()->first(), 'danger');

            return;
        }

        $this->toast(__('Setting updated.'));
    }

    public function saveBool(int $definitionId): void
    {
        $definition = SettingDefinition::findOrFail($definitionId);

        if (! $this->schoolScopeAllowedFor($definition)) {
            $this->toast(__('This setting can only be overridden at a narrower scope than school.'), 'danger');

            return;
        }

        $current = app(SettingResolver::class)->get($definition->key, $this->scopeChain());

        try {
            app(SetSettingValueAction::class)->execute(new SetSettingValueData(
                key: $definition->key,
                scopeType: SettingScope::School,
                scopeId: $this->school->id,
                value: ! (bool) $current,
                setByUserId: (int) Auth::id(),
                ipAddress: request()->ip(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Setting updated.'));
    }

    public function resetToInherited(int $definitionId): void
    {
        $definition = SettingDefinition::findOrFail($definitionId);

        app(ResetSettingToInheritedAction::class)->execute(new ResetSettingData(
            key: $definition->key,
            scopeType: SettingScope::School,
            scopeId: $this->school->id,
            performedByUserId: (int) Auth::id(),
            ipAddress: request()->ip(),
        ));

        unset($this->values[$definitionId]);

        $this->toast(__('Reset — this school now inherits the broader default.'));
    }

    public function render(): View
    {
        $modules = SettingDefinition::query()->distinct()->orderBy('module_code')->pluck('module_code');

        if ($this->activeModule === '' || ! $modules->contains($this->activeModule)) {
            $this->activeModule = (string) ($modules->first() ?? '');
        }

        $moduleCounts = SettingDefinition::query()
            ->selectRaw('module_code, count(*) as aggregate')
            ->groupBy('module_code')
            ->pluck('aggregate', 'module_code');

        $query = SettingDefinition::query()
            ->where('module_code', $this->activeModule)
            ->orderBy('group_key')
            ->orderBy('sort_order')
            ->orderBy('label');

        $term = trim($this->search);

        if ($term !== '') {
            $query->where(function ($q) use ($term): void {
                $q->where('key', 'like', "%{$term}%")->orWhere('label', 'like', "%{$term}%");
            });
        }

        $definitions = $query->get();

        $resolver = app(SettingResolver::class);
        $chain = $this->scopeChain();
        $resolved = [];

        foreach ($definitions as $definition) {
            if ($definition->is_encrypted) {
                continue;
            }

            $value = $resolver->get($definition->key, $chain);
            $resolved[$definition->id] = $value;

            if ($definition->data_type !== 'bool') {
                $this->values[$definition->id] ??= $this->stringifyForInput($definition, $value);
            }
        }

        return view('core::settings.index', [
            'modules' => $modules,
            'moduleCounts' => $moduleCounts,
            'groups' => $definitions->groupBy('group_key'),
            'resolved' => $resolved,
        ]);
    }

    protected function scopeChain(): ScopeChain
    {
        return new ScopeChain(schoolId: $this->school->id, tenantId: $this->school->tenant_id);
    }

    protected function schoolScopeAllowedFor(SettingDefinition $definition): bool
    {
        $lowestScope = SettingScope::from($definition->lowest_scope);

        return ! (SettingScope::School->isAtLeastAsGeneralAs($lowestScope) && $lowestScope !== SettingScope::School);
    }

    protected function hasOverrideFor(SettingDefinition $definition): bool
    {
        return SettingValue::where('setting_key', $definition->key)
            ->where('scope_type', SettingScope::School)
            ->where('scope_id', $this->school->id)
            ->exists();
    }

    private function stringifyForInput(SettingDefinition $definition, mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return (string) json_encode($value);
        }

        if ($value instanceof Carbon) {
            return $definition->data_type === 'time' ? $value->format('H:i') : $value->toDateString();
        }

        return (string) $value;
    }
}
