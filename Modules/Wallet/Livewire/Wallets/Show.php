<?php

declare(strict_types=1);

namespace Modules\Wallet\Livewire\Wallets;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Livewire\Concerns\ResolvesSystemAccounts;
use Modules\Finance\Models\Account;
use Modules\People\Models\StudentGuardian;
use Modules\Wallet\Domain\Actions\CloseWalletAction;
use Modules\Wallet\Domain\Actions\SetWalletControlsAction;
use Modules\Wallet\Domain\Actions\TopUpWalletAction;
use Modules\Wallet\Domain\DataObjects\CloseWalletData;
use Modules\Wallet\Domain\DataObjects\SetWalletControlsData;
use Modules\Wallet\Domain\DataObjects\TopUpWalletData;
use Modules\Wallet\Models\StudentWallet;
use Modules\Wallet\Models\WalletTransaction;

/**
 * `Wallet\Wallets\Show` (Book H3 FIN-14 §5/§6, `wallet.view` to view,
 * `wallet.manage` to set controls/top-up/close). Controls are set by
 * staff on behalf of the student's own fee-responsible guardian —
 * the same staff-recorded stand-in `Boarding\Exeats\Index` already
 * uses for an unbuilt guardian portal (`request_source =
 * phone_recorded`); `SetWalletControlsAction` itself still requires
 * a real `is_fee_responsible` link, never bypassed here. Also hosts
 * top-up (`TopUpWalletAction`) and wallet close
 * (`CloseWalletAction`, BR-FIN-14-016) — the top-up clearing account
 * and the refund/fee-debtors account on close are picked from the
 * chart since no single system key fits either (clearing varies by
 * payment method; fee debtors is the same `is_control_account`/
 * `subledger_type = 'student'` dropdown `Payroll\Run\Wizard` uses).
 */
#[Title('Student wallet')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use ResolvesSystemAccounts;
    use Toasts;

    public StudentWallet $wallet;

    public ?int $controlGuardianId = null;

    public string $dailyLimitMinor = '';

    public string $weeklyLimitMinor = '';

    public string $perTransactionLimitMinor = '';

    public string $blockedCategories = '';

    public string $lowBalanceThresholdMinor = '';

    public string $topUpAmountMinor = '';

    public ?int $clearingAccountId = null;

    public string $closePolicy = 'refund';

    public ?int $refundClearingAccountId = null;

    public ?int $feeDebtorsAccountId = null;

    public function mount(School $school, StudentWallet $wallet): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('wallet.view');

        abort_unless($wallet->school_id === $school->id, 404);

        $this->wallet = $wallet;
    }

    public function setControls(): void
    {
        $this->authorizePermission('wallet.manage');

        $this->validate(['controlGuardianId' => ['required', 'integer']]);

        try {
            app(SetWalletControlsAction::class)->execute(new SetWalletControlsData(
                walletId: $this->wallet->id,
                setByGuardianId: (int) $this->controlGuardianId,
                dailyLimitMinor: $this->dailyLimitMinor !== '' ? (int) $this->dailyLimitMinor : null,
                weeklyLimitMinor: $this->weeklyLimitMinor !== '' ? (int) $this->weeklyLimitMinor : null,
                perTransactionLimitMinor: $this->perTransactionLimitMinor !== '' ? (int) $this->perTransactionLimitMinor : null,
                blockedCategories: $this->blockedCategories !== '' ? array_map('trim', explode(',', $this->blockedCategories)) : null,
                lowBalanceThresholdMinor: $this->lowBalanceThresholdMinor !== '' ? (int) $this->lowBalanceThresholdMinor : null,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->wallet = $this->wallet->fresh();
        $this->toast(__('Controls updated.'));
    }

    public function topUp(): void
    {
        $this->authorizePermission('wallet.manage');

        $this->validate([
            'topUpAmountMinor' => ['required', 'integer', 'gt:0'],
            'clearingAccountId' => ['required', 'integer'],
        ]);

        app(TopUpWalletAction::class)->execute(new TopUpWalletData(
            walletId: $this->wallet->id,
            academicYearId: (int) SessionContext::yearId(),
            termId: (int) SessionContext::termId(),
            amountMinor: (int) $this->topUpAmountMinor,
            clearingAccountId: (int) $this->clearingAccountId,
            performedByUserId: (int) auth()->id(),
        ));

        $this->wallet = $this->wallet->fresh();
        $this->reset(['topUpAmountMinor']);
        $this->toast(__('Wallet topped up — no income recognised.'));
    }

    public function close(): void
    {
        $this->authorizePermission('wallet.manage');

        $this->validate(['closePolicy' => ['required', 'in:refund,transfer_to_fees']]);

        app(CloseWalletAction::class)->execute(new CloseWalletData(
            walletId: $this->wallet->id,
            academicYearId: (int) SessionContext::yearId(),
            termId: (int) SessionContext::termId(),
            policy: $this->closePolicy,
            performedByUserId: (int) auth()->id(),
            refundClearingAccountId: $this->refundClearingAccountId,
            feeDebtorsAccountId: $this->feeDebtorsAccountId,
        ));

        $this->wallet = $this->wallet->fresh();
        $this->toast(__('Wallet closed.'));
    }

    public function render(): View
    {
        return view('wallet::wallets.show', [
            'transactions' => WalletTransaction::where('wallet_id', $this->wallet->id)->orderByDesc('occurred_at')->limit(50)->get(),
            'feeResponsibleGuardians' => StudentGuardian::where('student_id', $this->wallet->student_id)
                ->where('is_fee_responsible', true)->where('status', 'active')->with('guardian')->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
            'feeDebtorsAccounts' => Account::where('school_id', $this->school->id)
                ->where('is_control_account', true)->where('subledger_type', 'student')->get(),
        ]);
    }
}
