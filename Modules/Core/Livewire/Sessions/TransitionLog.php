<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Sessions;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\PeriodStateTransition;
use Modules\Core\Models\School;

/**
 * `Core\Sessions\TransitionLog` (Book A CORE-03 §5). Read-only audit
 * trail of every `academic_state`/`financial_state` change for a school
 * — written exclusively by `TransitionPeriodStateAction`, the single
 * gateway for those columns.
 */
#[Title('Period transition log')]
#[Layout('layouts.app')]
final class TransitionLog extends Component
{
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use InteractsWithSession;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
    }

    public function render(): View
    {
        $query = PeriodStateTransition::query()
            ->where('school_id', $this->school->id)
            ->with(['term', 'performer', 'approver'])
            ->orderByDesc('id');

        return view('core::sessions.transition-log', [
            'transitions' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'term' => ['label' => __('Term')],
            'period_type' => [
                'label' => __('Type'), 'sortable' => true, 'filter' => 'select',
                'options' => ['academic' => __('Academic'), 'financial' => __('Financial')],
            ],
            'from_state' => ['label' => __('From'), 'sortable' => true],
            'to_state' => ['label' => __('To'), 'sortable' => true],
            'occurred_at' => ['label' => __('When'), 'sortable' => true],
            'performed_by' => ['label' => __('Performed by')],
        ];
    }
}
