<?php

declare(strict_types=1);

namespace Modules\Wallet\Livewire\TermEnd;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Wallet\Domain\Actions\ProcessTermEndWalletAction;
use Modules\Wallet\Domain\DataObjects\ProcessTermEndWalletData;
use Modules\Wallet\Models\StudentWallet;

/**
 * `Wallet\TermEnd\Process` (Book H3 FIN-14 §5 ⭐/BR-FIN-14-015,
 * `wallet.manage` ⚠). Preview — every active wallet with a non-zero
 * balance, read-only — then commit, which calls the real
 * `ProcessTermEndWalletAction` once per selected wallet. There is no
 * "absorb into income" option anywhere on this screen because none
 * exists in the Action itself (BR-FIN-14-015's own "not a reachable
 * code path").
 */
#[Title('Wallet term-end processing')]
#[Layout('layouts.app')]
final class Process extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $policy = 'carry_forward';

    /** @var array<int, int> */
    public array $selectedWalletIds = [];

    public ?int $refundClearingAccountId = null;

    public ?int $feeDebtorsAccountId = null;

    public bool $committed = false;

    public int $processedCount = 0;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('wallet.manage');
    }

    public function toggleAll(): void
    {
        $wallets = StudentWallet::where('school_id', $this->school->id)->where('status', 'active')->where('balance_minor', '!=', 0)->pluck('id');
        $this->selectedWalletIds = $this->selectedWalletIds === [] ? $wallets->all() : [];
    }

    public function commit(): void
    {
        if ($this->selectedWalletIds === []) {
            $this->toast(__('Select at least one wallet.'), 'danger');

            return;
        }

        $yearId = (int) SessionContext::yearId();
        $termId = (int) SessionContext::termId();
        $count = 0;

        foreach ($this->selectedWalletIds as $walletId) {
            app(ProcessTermEndWalletAction::class)->execute(new ProcessTermEndWalletData(
                walletId: $walletId,
                academicYearId: $yearId,
                termId: $termId,
                policy: $this->policy,
                performedByUserId: (int) auth()->id(),
                refundClearingAccountId: $this->refundClearingAccountId,
                feeDebtorsAccountId: $this->feeDebtorsAccountId,
            ));
            $count++;
        }

        $this->processedCount = $count;
        $this->committed = true;
        $this->selectedWalletIds = [];
        $this->toast(__(':count wallet(s) processed.', ['count' => $count]));
    }

    public function render(): View
    {
        return view('wallet::term-end.process', [
            'wallets' => StudentWallet::where('school_id', $this->school->id)->where('status', 'active')->where('balance_minor', '!=', 0)->with('student')->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
            'feeDebtorsAccounts' => Account::where('school_id', $this->school->id)->where('is_control_account', true)->where('subledger_type', 'student')->get(),
        ]);
    }
}
