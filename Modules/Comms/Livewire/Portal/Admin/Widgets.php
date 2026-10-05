<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Portal\Admin;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\SetWidgetConfigurationAction;
use Modules\Comms\Domain\DataObjects\WidgetDefinition;
use Modules\Comms\Domain\Registry\WidgetRegistry;
use Modules\Comms\Domain\Support\EnabledWidgetsResolver;
use Modules\Comms\Models\SchoolWidgetSetting;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Portal\Admin\Widgets` (Book I COM-03/04/05 §7,
 * `portal.widget.view` / `portal.widget.manage`) — the one admin-UI
 * screen of the portal services. The parent, learner and staff
 * dashboards, onboarding wizard and device screens are consumed by the
 * Next.js and Flutter apps over the API, not built in Livewire (this
 * panel is internal staff only); device management is `CORE-05`'s own
 * `Profile\Devices`. Here a school enables and reorders widgets per
 * persona (BR-COM-03-004).
 *
 * Only widgets whose owning module the school has enabled are offered
 * (BR-COM-03-003, AC-COM-03-002), via the resolver's own filter. The
 * learner safeguarding "tell someone" entry point is not a widget and
 * is not listed, so no configuration can remove it (AC-COM-03-004).
 * `portal.dashboard_widget_max_per_persona` caps how many a school may
 * enable. A widget with no saved row shows its registered default.
 */
#[Title('Portal widgets')]
#[Layout('layouts.app')]
final class Widgets extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $persona = 'parent';

    /**
     * @var array<string, array{enabled: bool, sort: int}>
     */
    public array $rows = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('portal.widget.view');

        $this->loadRows();
    }

    public function updatedPersona(): void
    {
        if (! in_array($this->persona, ['parent', 'learner', 'staff'], true)) {
            $this->persona = 'parent';
        }

        $this->loadRows();
    }

    public function save(): void
    {
        $this->authorizePermission('portal.widget.manage');

        $this->validate([
            'rows' => ['array'],
            'rows.*.enabled' => ['boolean'],
            'rows.*.sort' => ['integer', 'min:0', 'max:999'],
        ]);

        $available = $this->availableWidgets();
        $max = $this->maxWidgetsPerPersona();
        $enabledCount = collect($available)->filter(fn ($widget): bool => (bool) ($this->rows[$widget->key]['enabled'] ?? false))->count();

        if ($enabledCount > $max) {
            $this->addError('rows', __('A persona dashboard can show at most :max widgets; :count are enabled.', ['max' => $max, 'count' => $enabledCount]));

            return;
        }

        $action = app(SetWidgetConfigurationAction::class);

        foreach ($available as $widget) {
            $row = $this->rows[$widget->key] ?? null;

            if ($row === null) {
                continue;
            }

            $action->execute($this->school->id, $widget->key, $this->persona, (bool) $row['enabled'], (int) $row['sort']);
        }

        $this->toast(__('Widget configuration saved.'));
    }

    public function render(): View
    {
        $available = $this->availableWidgets();
        $hidden = array_values(array_filter(
            WidgetRegistry::forPersona($this->persona),
            fn ($widget): bool => ! in_array($widget->key, array_map(fn ($w): string => $w->key, $available), true),
        ));

        return view('comms::portal.admin.widgets', [
            'widgets' => $available,
            'unavailableCount' => count($hidden),
            'max' => $this->maxWidgetsPerPersona(),
            'canManage' => app(PermissionScopeResolver::class)->has(auth()->user(), 'portal.widget.manage', PermissionScope::Own),
        ]);
    }

    private function loadRows(): void
    {
        $settings = SchoolWidgetSetting::where('school_id', $this->school->id)->where('persona', $this->persona)->get()->keyBy('widget_key');

        $this->rows = [];

        foreach ($this->availableWidgets() as $widget) {
            $setting = $settings->get($widget->key);

            $this->rows[$widget->key] = [
                'enabled' => $setting !== null ? $setting->is_enabled : $widget->defaultEnabled,
                'sort' => $setting !== null && $setting->sort_order !== null ? $setting->sort_order : $widget->defaultSortOrder,
            ];
        }
    }

    /**
     * @return array<int, WidgetDefinition>
     */
    private function availableWidgets(): array
    {
        return app(EnabledWidgetsResolver::class)->availableForConfiguration($this->persona, $this->school->id);
    }

    private function maxWidgetsPerPersona(): int
    {
        return (int) app(SettingResolver::class)->get('portal.dashboard_widget_max_per_persona', new ScopeChain(schoolId: $this->school->id));
    }
}
