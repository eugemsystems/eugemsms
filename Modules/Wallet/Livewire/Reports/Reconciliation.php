<?php

declare(strict_types=1);

namespace Modules\Wallet\Livewire\Reports;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Wallet\Domain\Actions\ReconcileWalletLiabilityAction;

/**
 * `Wallet\Reports\Reconciliation` (Book H3 FIN-14 §6/BR-FIN-14-019,
 * `wallet.report.view`). Runs the real `ReconcileWalletLiabilityAction`
 * on demand — the same check `FIN-12`'s own close checklist calls
 * (`WalletLiabilityReconcilesCheck`).
 */
#[Title('Wallet liability reconciliation')]
#[Layout('layouts.app')]
final class Reconciliation extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    /** @var array{sum_of_balances_minor: int, liability_account_balance_minor: int, variance_minor: int}|null */
    public ?array $result = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('wallet.report.view');
    }

    public function reconcile(): void
    {
        $this->result = app(ReconcileWalletLiabilityAction::class)->execute($this->school->id);
    }

    public function render(): View
    {
        return view('wallet::reports.reconciliation');
    }
}
