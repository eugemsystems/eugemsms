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
use Modules\Core\Models\PeriodSnapshot;
use Modules\Core\Models\School;

/**
 * `Core\Sessions\Snapshots` (Book A CORE-03 §5). Read-only, append-only
 * tamper-evident snapshots (`PeriodSnapshot` is guarded against update
 * and delete at the model layer itself — see that model's `booted()`) —
 * this screen never writes one; only `TakePeriodSnapshotAction` does.
 */
#[Title('Period snapshots')]
#[Layout('layouts.app')]
final class Snapshots extends Component
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

    public function view(int $snapshotId): void
    {
        $this->viewingId = $snapshotId;
    }

    public function render(): View
    {
        $query = PeriodSnapshot::query()
            ->where('school_id', $this->school->id)
            ->with(['academicYear', 'term', 'takenBy'])
            ->orderByDesc('id');

        $viewing = $this->viewingId !== null ? PeriodSnapshot::find($this->viewingId) : null;

        return view('core::sessions.snapshots', [
            'snapshots' => $this->paginateDataTable($query, $this->tableColumns()),
            'viewing' => $viewing,
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'term' => ['label' => __('Term')],
            'snapshot_type' => ['label' => __('Type'), 'sortable' => true, 'searchable' => true],
            'taken_at' => ['label' => __('Taken'), 'sortable' => true],
            'taken_by' => ['label' => __('Taken by')],
        ];
    }
}
