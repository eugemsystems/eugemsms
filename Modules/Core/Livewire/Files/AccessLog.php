<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Files;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\FileAccessLogEntry;
use Modules\Core\Models\School;

/**
 * `Core\Files\AccessLog` (Book A CORE-10 BR-CORE-10-007,
 * `core.file.view_access_log`) — every recorded view/download of a
 * sensitive-category file, written by `RecordFileAccessAction`. `File`
 * itself carries no `school_id`-free path here since `file_access_log`
 * has no `school_id` column of its own (BR-CORE-10-007's data model) —
 * scoped via a `whereHas` on the owning file instead.
 */
#[Title('File access log')]
#[Layout('layouts.app')]
final class AccessLog extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.file.view_access_log');
    }

    public function render(): View
    {
        $schoolId = $this->school->id;

        $query = FileAccessLogEntry::query()
            ->whereHas('file', fn ($q) => $q->where('school_id', $schoolId))
            ->with(['file', 'user']);

        return view('core::files.access-log', [
            'entries' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'file' => ['label' => __('File')],
            'user' => ['label' => __('User')],
            'action' => [
                'label' => __('Action'), 'sortable' => true, 'filter' => 'select',
                'options' => ['view' => __('View'), 'download' => __('Download')],
            ],
            'ip_address' => ['label' => __('IP address')],
            'accessed_at' => ['label' => __('When'), 'sortable' => true],
        ];
    }
}
