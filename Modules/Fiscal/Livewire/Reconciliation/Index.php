<?php

declare(strict_types=1);

namespace Modules\Fiscal\Livewire\Reconciliation;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Fiscal\Domain\Actions\ReconcileFiscalisationAction;
use Modules\Fiscal\Models\FiscalReceipt;

/**
 * `Fiscal\Reconciliation\Index` (Book H3 FIN-13 §6/§7 ⭐,
 * `fiscal.view`). Runs the real `ReconcileFiscalisationAction` on
 * demand — the same check `FIN-12`'s own close checklist calls
 * (`FiscalisationReconciledCheck`). No cross-module query of
 * `receipts`/`farm_sales` is needed here either: every fiscalisable
 * commercial receipt already got a real `FiscalReceipt` row the
 * moment it was routed, so the exception set is simply every row not
 * yet `accepted` and older than the configured window.
 */
#[Title('Fiscalisation reconciliation')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    /** @var array<int, FiscalReceipt> */
    public array $unreconciled = [];

    public bool $checked = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('fiscal.view');
    }

    public function reconcile(): void
    {
        $this->unreconciled = app(ReconcileFiscalisationAction::class)->execute($this->school->id)->all();
        $this->checked = true;
    }

    public function render(): View
    {
        return view('fiscal::reconciliation.index');
    }
}
