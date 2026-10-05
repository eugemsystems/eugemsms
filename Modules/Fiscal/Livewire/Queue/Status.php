<?php

declare(strict_types=1);

namespace Modules\Fiscal\Livewire\Queue;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Fiscal\Domain\Actions\DrainOfflineFiscalQueueAction;
use Modules\Fiscal\Models\FiscalReceipt;

/**
 * `Fiscal\Queue\Status` (Book H3 FIN-13 §4/§7, `fiscal.view` to
 * view, `fiscal.retry` to drain). Queue depth and oldest item are
 * read directly off `offline_queued` receipts; drain runs the real
 * `DrainOfflineFiscalQueueAction`, which retries oldest-`global_counter`
 * first (BR-FIN-13-009's "in counter order") and fires
 * `OfflineQueueBacklog` itself when depth crosses the configured
 * alert threshold — this screen surfaces the count, it does not
 * recompute the alert.
 */
#[Title('Offline fiscal queue')]
#[Layout('layouts.app')]
final class Status extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('fiscal.view');
    }

    public function drain(): void
    {
        $this->authorizePermission('fiscal.retry');

        $drained = app(DrainOfflineFiscalQueueAction::class)->execute($this->school->id);
        $this->toast(__(':count receipt(s) processed.', ['count' => $drained->count()]));
    }

    public function render(): View
    {
        $queued = FiscalReceipt::where('school_id', $this->school->id)->where('status', 'offline_queued')->orderBy('global_counter')->get();

        return view('fiscal::queue.status', [
            'queued' => $queued,
            'depth' => $queued->count(),
            'oldest' => $queued->first(),
        ]);
    }
}
