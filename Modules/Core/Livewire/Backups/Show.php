<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Backups;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Backups\ApproveProductionRestoreAction;
use Modules\Core\Domain\Actions\Backups\RequestProductionRestoreAction;
use Modules\Core\Domain\Actions\Backups\RunRestoreTestAction;
use Modules\Core\Domain\DataObjects\Backups\ApproveProductionRestoreData;
use Modules\Core\Domain\DataObjects\Backups\RequestProductionRestoreData;
use Modules\Core\Domain\DataObjects\Backups\RunRestoreTestData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Models\Backup;

/**
 * `Core\Backups\Show` (Book A CORE-13 §5) — one backup's detail and its
 * full restore-test history, including the dual-authorisation flow for
 * a production restore (BR-CORE-13-009). Same tenant-wide authorisation
 * gap as `Index`.
 */
#[Title('Backup detail')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use Toasts;

    public Backup $backup;

    public bool $showRequestModal = false;

    public string $reason = '';

    public function mount(Backup $backup): void
    {
        $this->backup = $backup;
    }

    public function runRestoreTest(): void
    {
        try {
            $test = app(RunRestoreTestAction::class)->execute(new RunRestoreTestData($this->backup->id));
            $this->backup = $this->backup->fresh();
            $this->toast($test->passed() ? __('Restore test passed.') : __('Restore test failed.'), $test->passed() ? 'success' : 'danger');
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    public function requestProductionRestore(): void
    {
        $this->validate(['reason' => ['required', 'string', 'min:10']]);

        try {
            app(RequestProductionRestoreAction::class)->execute(new RequestProductionRestoreData(
                backupId: $this->backup->id,
                requestedByUserId: (int) Auth::id(),
                reason: $this->reason,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->showRequestModal = false;
        $this->reason = '';
        $this->toast(__('Production restore requested — awaiting a second approver.'));
    }

    public function approveProductionRestore(int $restoreTestId): void
    {
        try {
            app(ApproveProductionRestoreAction::class)->execute(new ApproveProductionRestoreData(
                restoreTestId: $restoreTestId,
                approvedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Production restore approved.'));
    }

    public function render(): View
    {
        return view('core::backups.show', [
            'restoreTests' => $this->backup->restoreTests()->orderByDesc('id')->with(['requestedBy', 'approvedBy'])->get(),
        ]);
    }
}
