<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Scheduling;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Scheduling\CheckScheduledTaskFreshnessAction;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Models\ScheduledTask;

/**
 * `Core\Scheduling\Tasks` (Book A CORE-12 §5, `core.scheduling.view`) —
 * every registered scheduled task with its last run and freshness.
 *
 * Tenant-wide with no `{school}` of its own — scheduling/health/queue
 * data belongs to the whole installation, not one tenant, so there is
 * no ambient school to authorise against in the first place, the same
 * "no 'any school' resolver yet" gap `.ai/rules/auth.md` documents for
 * `Users\Index`/`Show`/`Form`/`LoginAudit` and `Schools\Index`
 * (whichever school id `PermissionScopeResolver` were given here would
 * be arbitrary, not "the" school this data belongs to — there isn't
 * one). Authenticated-only for now, the same accepted interim state
 * every one of those screens is already in.
 */
#[Title('Scheduled tasks')]
#[Layout('layouts.app')]
final class Tasks extends Component
{
    use InteractsWithDataTable;
    use Toasts;

    public function checkFreshness(): void
    {
        $stale = app(CheckScheduledTaskFreshnessAction::class)->execute();

        $this->toast(
            $stale === []
                ? __('Every task has run within its expected window.')
                : __(':count task(s) are stale: :keys', ['count' => count($stale), 'keys' => collect($stale)->pluck('key')->implode(', ')]),
            $stale === [] ? 'success' : 'danger',
        );
    }

    public function render(): View
    {
        $query = ScheduledTask::query()->with('latestRun');

        return view('core::scheduling.tasks', [
            'tasks' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'name' => ['label' => __('Task'), 'sortable' => true, 'searchable' => true],
            'module_code' => ['label' => __('Module'), 'sortable' => true],
            'schedule_expression' => ['label' => __('Schedule')],
            'is_enabled' => [
                'label' => __('Enabled'), 'sortable' => true, 'filter' => 'select',
                'options' => ['1' => __('Yes'), '0' => __('No')],
            ],
            'is_per_school' => ['label' => __('Per school')],
            'latest_run' => ['label' => __('Last run')],
        ];
    }
}
