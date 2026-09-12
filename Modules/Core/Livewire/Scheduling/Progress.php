<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Scheduling;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Scheduling\CancelJobProgressAction;
use Modules\Core\Domain\DataObjects\Scheduling\CancelJobProgressData;
use Modules\Core\Models\JobProgress;

/**
 * `Core\Scheduling\Progress` (Book A CORE-12 §5/BR-CORE-12-005/
 * AC-CORE-12-004) — the current user's own long-running jobs, live
 * counters and current step, polled rather than pushed (`wire:poll` in
 * the view — no websocket/broadcast layer exists yet for this). Scoped
 * to the viewer's own `user_id`, not a school — a `job_progress` row
 * already carries `school_id` per-job as data, but "jobs I'm waiting
 * on" is inherently a personal list, not a per-school one.
 */
#[Title('My jobs')]
#[Layout('layouts.app')]
final class Progress extends Component
{
    use Toasts;

    public function cancel(int $jobProgressId): void
    {
        $job = JobProgress::where('user_id', Auth::id())->findOrFail($jobProgressId);

        app(CancelJobProgressAction::class)->execute(new CancelJobProgressData($job->id));

        $this->toast(__('Job cancelled.'));
    }

    public function render(): View
    {
        return view('core::scheduling.progress', [
            'jobs' => $this->jobs(),
        ]);
    }

    /**
     * @return Collection<int, JobProgress>
     */
    private function jobs(): Collection
    {
        return JobProgress::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }
}
