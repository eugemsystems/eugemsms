<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Numbering;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\NumberingSeries;
use Modules\Core\Models\School;

/**
 * `Core\Numbering\Index` (Book A CORE-06 §6, `core.numbering.view`).
 * Lists every numbering series this school owns — one row per
 * (document_type, academic_year, term) combination. A series's
 * document type and period scope are fixed at creation (BR-CORE-06-005
 * implies the period can't move once numbers are allocated against
 * it) — `Editor` only lets pattern/prefix/padding/reset-policy/active
 * change on an existing series.
 */
#[Title('Numbering series')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.numbering.view');
    }

    public function render(): View
    {
        $query = NumberingSeries::query()->where('school_id', $this->school->id);

        return view('core::numbering.index', [
            'series' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'document_type' => ['label' => __('Document type'), 'column' => 'document_type', 'sortable' => true, 'searchable' => true],
            'pattern' => ['label' => __('Pattern'), 'column' => 'pattern'],
            'next_sequence' => ['label' => __('Next number'), 'column' => 'next_sequence', 'sortable' => true],
            'reset_policy' => [
                'label' => __('Reset policy'), 'sortable' => true, 'filter' => 'select',
                'options' => ['never' => __('Never'), 'yearly' => __('Yearly'), 'termly' => __('Termly')],
            ],
            'is_active' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => ['1' => __('Active'), '0' => __('Inactive')],
            ],
        ];
    }
}
