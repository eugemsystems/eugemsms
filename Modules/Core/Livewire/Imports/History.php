<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Imports;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\ImportBatch;
use Modules\Core\Models\School;

/**
 * `Core\Import\History` (Book A CORE-11 §5/BR-CORE-11-013,
 * `core.import.view`) — every batch ever run for this school, source
 * file retained alongside it. Drilling into a row goes to
 * `Core\Import\Batch`, which renders whatever that batch's own status
 * calls for (report, result, or a rollback button) rather than
 * duplicating that rendering here.
 */
#[Title('Import history')]
#[Layout('layouts.app')]
final class History extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.import.view');
    }

    public function render(): View
    {
        $query = ImportBatch::query()->where('school_id', $this->school->id)->with('importedBy');

        return view('core::imports.history', [
            'batches' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'definition_key' => ['label' => __('Import'), 'sortable' => true, 'searchable' => true],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => [
                    'mapping' => __('Mapping'), 'validating' => __('Validating'), 'validated' => __('Validated'),
                    'importing' => __('Importing'), 'completed' => __('Completed'), 'failed' => __('Failed'),
                    'rolled_back' => __('Rolled back'),
                ],
            ],
            'imported_rows' => ['label' => __('Imported'), 'sortable' => true],
            'failed_rows' => ['label' => __('Failed'), 'sortable' => true],
            'imported_by' => ['label' => __('By')],
            'started_at' => ['label' => __('Started'), 'sortable' => true],
        ];
    }
}
