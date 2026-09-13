<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Fees;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\FeeStructure;

/**
 * `Finance\Fees\Structures` (Book B FIN-02 §7, `finance.fee_structure.view`)
 * — every structure this school has ever defined, every version. Only
 * the current row per (name) is what a bursar usually cares about, but
 * superseded/archived rows stay visible here — they're still linked to
 * the assignments they produced (BR-FIN-02-011).
 */
#[Title('Fee structures')]
#[Layout('layouts.app')]
final class Structures extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.fee_structure.view');
    }

    public function render(): View
    {
        $query = FeeStructure::query()->with('academicYear', 'term')->orderByDesc('id');

        return view('finance::fees.structures', [
            'structures' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'name' => ['label' => __('Name'), 'sortable' => true, 'searchable' => true],
            'version' => ['label' => __('Version'), 'sortable' => true],
            'priority' => ['label' => __('Priority'), 'sortable' => true],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => ['draft' => __('Draft'), 'active' => __('Active'), 'superseded' => __('Superseded'), 'archived' => __('Archived')],
            ],
        ];
    }
}
