<?php

declare(strict_types=1);

namespace Modules\Wallet\Livewire\Wallets;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Livewire\Concerns\ResolvesSystemAccounts;
use Modules\People\Models\Student;
use Modules\Wallet\Domain\Actions\CreateStudentWalletAction;
use Modules\Wallet\Domain\DataObjects\CreateStudentWalletData;
use Modules\Wallet\Models\StudentWallet;

/**
 * `Wallet\Wallets\Index` (Book H3 FIN-14 §2/§6, `wallet.view` to
 * view, `wallet.manage` to create). Named `Wallets`, not the spec's
 * own `Accounts` — `Accounts/Index` already occupies that exact
 * relative path in `Modules\Utilities` (Book H2 OPS-04), caught by
 * this pass's own standing duplicate-component-name check before
 * this file was written. The wallet's own `liability_account_id`
 * resolves by the dedicated `wallet_liability` `system_key` (set
 * once via `Finance\Accounts\Editor`) rather than a per-wallet
 * dropdown — there can be hundreds of learners, and every wallet's
 * balance sits against the exact same liability account.
 */
#[Title('Student wallets')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesSystemAccounts;
    use Toasts;

    public ?int $studentId = null;

    public string $currency = 'USD';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('wallet.view');
    }

    public function create(): void
    {
        $this->authorizePermission('wallet.manage');

        $this->validate([
            'studentId' => ['required', 'integer'],
            'currency' => ['required', 'in:USD,ZWG'],
        ]);

        $liabilityAccountId = $this->requireSystemAccount('wallet_liability', 'Student Wallet Liability');

        app(CreateStudentWalletAction::class)->execute(new CreateStudentWalletData(
            schoolId: $this->school->id,
            studentId: (int) $this->studentId,
            currency: $this->currency,
            liabilityAccountId: $liabilityAccountId,
        ));

        $this->reset(['studentId']);
        $this->toast(__('Wallet created.'));
    }

    public function render(): View
    {
        return view('wallet::wallets.index', [
            'wallets' => StudentWallet::where('school_id', $this->school->id)->with('student')->orderByDesc('id')->limit(100)->get(),
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(200)->get(),
        ]);
    }
}
