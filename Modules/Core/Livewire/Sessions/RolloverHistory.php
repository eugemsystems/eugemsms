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
use Modules\Core\Models\PeriodRollover;
use Modules\Core\Models\School;

/**
 * `Core\Sessions\RolloverHistory` (Book A CORE-03 §5). Read-only record
 * of every roll-over attempt for a school — `RolloverWizard` is the only
 * screen that ever writes one of these.
 */
#[Title('Rollover history')]
#[Layout('layouts.app')]
final class RolloverHistory extends Component
{
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use InteractsWithSession;

    public ?int $viewingId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
    }

    public function view(int $rolloverId): void
    {
        $this->viewingId = $rolloverId;
    }

    public function render(): View
    {
        $query = PeriodRollover::query()
            ->where('school_id', $this->school->id)
            ->with(['fromTerm', 'toTerm', 'initiator', 'approver'])
            ->orderByDesc('id');

        $viewing = $this->viewingId !== null
            ? PeriodRollover::with(['fromTerm', 'toTerm'])->find($this->viewingId)
            : null;

        return view('core::sessions.rollover-history', [
            'rollovers' => $this->paginateDataTable($query, $this->tableColumns()),
            'viewing' => $viewing,
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => [
                    'pending' => __('Pending'), 'validating' => __('Validating'), 'running' => __('Running'),
                    'completed' => __('Completed'), 'failed' => __('Failed'), 'rolled_back' => __('Rolled back'),
                ],
            ],
            'started_at' => ['label' => __('Started'), 'sortable' => true],
            'completed_at' => ['label' => __('Completed'), 'sortable' => true],
        ];
    }
}
