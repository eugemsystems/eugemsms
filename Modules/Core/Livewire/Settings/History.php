<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Settings;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\SettingChangeLog;

/**
 * `Core\Settings\History` (Book A CORE-04 §5). The append-only
 * `setting_change_log`, filtered to School-scope entries for this
 * school — narrower (term/user/…) scoped changes aren't shown here yet,
 * matching `Settings\Edit`'s own School-only scope.
 */
#[Title('Setting change history')]
#[Layout('layouts.app')]
final class History extends Component
{
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function render(): View
    {
        $query = SettingChangeLog::query()
            ->where('scope_type', SettingScope::School)
            ->where('scope_id', $this->school->id)
            ->with('changedBy')
            ->orderByDesc('id');

        return view('core::settings.history', [
            'entries' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'setting_key' => ['label' => __('Setting'), 'sortable' => true, 'searchable' => true],
            'old_value' => ['label' => __('Old value')],
            'new_value' => ['label' => __('New value')],
            'changed_at' => ['label' => __('When'), 'sortable' => true],
            'changed_by' => ['label' => __('Changed by')],
        ];
    }
}
