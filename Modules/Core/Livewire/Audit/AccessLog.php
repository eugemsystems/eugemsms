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
use Modules\Core\Models\DataAccessLogEntry;
use Modules\Core\Models\School;

/**
 * `Core\Audit\AccessLog` (Book A CORE-08 §5/BR-CORE-08-009,
 * `core.audit.view_access`) — every recorded read of medical,
 * safeguarding, payroll, or bulk learner data.
 */
#[Title('Data access log')]
#[Layout('layouts.app')]
final class AccessLog extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.audit.view_access');
    }

    public function render(): View
    {
        $query = DataAccessLogEntry::query()->where('school_id', $this->school->id)->with('user');

        return view('core::audit.access-log', [
            'entries' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'resource_type' => ['label' => __('Resource'), 'sortable' => true, 'searchable' => true],
            'access_type' => [
                'label' => __('Access'), 'sortable' => true, 'filter' => 'select',
                'options' => ['view' => __('View'), 'export' => __('Export'), 'print' => __('Print'), 'download' => __('Download'), 'search' => __('Search')],
            ],
            'user' => ['label' => __('User')],
            'record_count' => ['label' => __('Records'), 'sortable' => true],
            'purpose' => ['label' => __('Purpose'), 'searchable' => true],
            'accessed_at' => ['label' => __('When'), 'sortable' => true],
        ];
    }
}
