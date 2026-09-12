<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Scheduling;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Models\FailedJob;

/**
 * `Core\Scheduling\FailedJobs` (Book A CORE-12 §5/BR-CORE-12-004,
 * `core.scheduling.manage`) — every job in Laravel's own `failed_jobs`
 * table, retryable individually or in bulk. Retry/forget delegate to
 * the real `queue:retry`/`queue:forget` artisan commands rather than
 * reimplementing failed-job requeueing — those commands already handle
 * decrypting the stored payload and re-pushing it onto its original
 * queue/connection correctly.
 */
#[Title('Failed jobs')]
#[Layout('layouts.app')]
final class FailedJobs extends Component
{
    use InteractsWithDataTable;
    use Toasts;

    /**
     * @var array<int, string>
     */
    public array $selected = [];

    public function retry(string $uuid): void
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        $this->toast(__('Job queued for retry.'));
    }

    public function retrySelected(): void
    {
        foreach ($this->selected as $uuid) {
            Artisan::call('queue:retry', ['id' => [$uuid]]);
        }

        $count = count($this->selected);
        $this->selected = [];
        $this->toast(__(':count job(s) queued for retry.', ['count' => $count]));
    }

    public function forget(string $uuid): void
    {
        Artisan::call('queue:forget', ['id' => $uuid]);
        $this->toast(__('Failed job removed.'));
    }

    public function render(): View
    {
        $query = FailedJob::query();

        return view('core::scheduling.failed-jobs', [
            'jobs' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'connection' => ['label' => __('Connection'), 'sortable' => true, 'filter' => 'text'],
            'queue' => ['label' => __('Queue'), 'sortable' => true, 'filter' => 'text'],
            'exception' => ['label' => __('Exception')],
            'failed_at' => ['label' => __('Failed'), 'sortable' => true],
        ];
    }
}
