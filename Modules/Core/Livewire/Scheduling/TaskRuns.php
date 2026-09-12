<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Scheduling;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Models\ScheduledTask;
use Modules\Core\Models\ScheduledTaskRun;

/**
 * `Core\Scheduling\TaskRuns` (Book A CORE-12 §5) — run history for one
 * scheduled task. `{taskKey}` is a plain route-string bound to
 * `ScheduledTask.key` in `mount()` rather than implicit model binding —
 * `ScheduledTask` has no `ulid` (the spec's own §2 data model omits one,
 * unlike its sibling CORE-12 tables), and `key` is already a stable,
 * non-sequential natural identifier, so routing on it directly needs no
 * new column. Same tenant-wide authorisation gap as `Tasks`.
 */
#[Title('Scheduled task runs')]
#[Layout('layouts.app')]
final class TaskRuns extends Component
{
    use InteractsWithDataTable;

    public ScheduledTask $task;

    public function mount(string $taskKey): void
    {
        $this->task = ScheduledTask::where('key', $taskKey)->firstOrFail();
    }

    public function render(): View
    {
        $query = ScheduledTaskRun::query()->where('task_id', $this->task->id);

        return view('core::scheduling.task-runs', [
            'runs' => $this->paginateDataTable($query, $this->tableColumns()),
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
                'options' => ['running' => __('Running'), 'completed' => __('Completed'), 'failed' => __('Failed'), 'timed_out' => __('Timed out')],
            ],
            'started_at' => ['label' => __('Started'), 'sortable' => true],
            'duration_ms' => ['label' => __('Duration (ms)'), 'sortable' => true],
            'error' => ['label' => __('Error')],
        ];
    }
}
