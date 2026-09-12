<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Audit;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\School;

/**
 * `Core\Audit\Explorer` (Book A CORE-08 §5, `core.audit.view`) — every
 * `activity_log` entry for this school, filterable by module (log
 * name), event, causer, and date (BR-CORE-08-003's `request_id` is
 * shown per row so every change from one request can be spotted, per
 * the spec, without a dedicated "group by request" view here).
 */
#[Title('Audit explorer')]
#[Layout('layouts.app')]
final class Explorer extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.audit.view');
    }

    public function render(): View
    {
        $query = ActivityLogEntry::query()->where('school_id', $this->school->id)->with('causer');

        return view('core::audit.explorer', [
            'entries' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'log_name' => ['label' => __('Module'), 'sortable' => true, 'searchable' => true],
            'description' => ['label' => __('Description'), 'searchable' => true],
            'event' => [
                'label' => __('Event'), 'sortable' => true, 'filter' => 'select',
                'options' => ['created' => __('Created'), 'updated' => __('Updated'), 'deleted' => __('Deleted'), 'restored' => __('Restored')],
            ],
            'subject_type' => ['label' => __('Subject'), 'searchable' => true],
            'created_at' => ['label' => __('When'), 'sortable' => true],
        ];
    }
}
