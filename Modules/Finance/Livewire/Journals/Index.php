<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Journals;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\Journal;

/**
 * `Finance\Journals\Index` (Book B FIN-01 §8, `finance.journal.view`) —
 * every journal posted or drafted this school, newest first.
 */
#[Title('Journals')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.journal.view');
    }

    public function render(): View
    {
        $query = Journal::query()->orderByDesc('effective_at')->orderByDesc('id');

        return view('finance::journals.index', [
            'journals' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'journal_number' => ['label' => __('Number'), 'sortable' => true, 'searchable' => true],
            'journal_type' => ['label' => __('Type'), 'sortable' => true, 'searchable' => true],
            'narration' => ['label' => __('Narration'), 'searchable' => true],
            'effective_at' => ['label' => __('Effective'), 'sortable' => true],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => ['draft' => __('Draft'), 'posted' => __('Posted')],
            ],
        ];
    }
}
