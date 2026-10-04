<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Bank;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CreateBankAccountAction;
use Modules\Finance\Domain\Actions\UpdateBankAccountAction;
use Modules\Finance\Domain\DataObjects\CreateBankAccountData;
use Modules\Finance\Domain\DataObjects\UpdateBankAccountData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\BankAccount;

/**
 * `Finance\Bank\Accounts` (Book B FIN-05 §3, `finance.bank.manage`).
 */
#[Title('Bank accounts')]
#[Layout('layouts.app')]
final class Accounts extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public bool $showFormModal = false;

    public ?int $editingBankAccountId = null;

    public ?int $glAccountId = null;

    public string $bankName = '';

    public string $accountName = '';

    public string $accountNumber = '';

    public string $branch = '';

    public string $currency = 'USD';

    public string $accountType = 'current';

    public bool $isActive = true;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.bank.manage');
    }

    public function openCreateModal(): void
    {
        $this->reset(['editingBankAccountId', 'glAccountId', 'bankName', 'accountName', 'accountNumber', 'branch']);
        $this->currency = $this->school->base_currency ?? 'USD';
        $this->accountType = 'current';
        $this->isActive = true;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function openEditModal(int $bankAccountId): void
    {
        $bankAccount = BankAccount::where('school_id', $this->school->id)->findOrFail($bankAccountId);

        $this->editingBankAccountId = $bankAccount->id;
        $this->glAccountId = $bankAccount->gl_account_id;
        $this->bankName = $bankAccount->bank_name;
        $this->accountName = $bankAccount->account_name;
        $this->accountNumber = $bankAccount->account_number;
        $this->branch = (string) $bankAccount->branch;
        $this->currency = $bankAccount->currency;
        $this->accountType = $bankAccount->account_type;
        $this->isActive = $bankAccount->is_active;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'glAccountId' => ['required', 'integer'],
            'bankName' => ['required', 'string', 'max:120'],
            'accountName' => ['required', 'string', 'max:200'],
            'accountNumber' => ['required', 'string', 'max:60'],
            'currency' => ['required', 'string', 'size:3'],
            'accountType' => ['required', 'in:current,nostro,fca,savings'],
        ]);

        try {
            if ($this->editingBankAccountId === null) {
                app(CreateBankAccountAction::class)->execute(new CreateBankAccountData(
                    schoolId: $this->school->id,
                    glAccountId: (int) $this->glAccountId,
                    bankName: $this->bankName,
                    accountName: $this->accountName,
                    accountNumber: $this->accountNumber,
                    currency: $this->currency,
                    accountType: $this->accountType,
                    branch: $this->branch !== '' ? $this->branch : null,
                    isActive: $this->isActive,
                ));
            } else {
                app(UpdateBankAccountAction::class)->execute(new UpdateBankAccountData(
                    bankAccountId: $this->editingBankAccountId,
                    glAccountId: (int) $this->glAccountId,
                    bankName: $this->bankName,
                    accountName: $this->accountName,
                    accountNumber: $this->accountNumber,
                    currency: $this->currency,
                    accountType: $this->accountType,
                    branch: $this->branch !== '' ? $this->branch : null,
                    isActive: $this->isActive,
                ));
            }
        } catch (DomainException $e) {
            $this->addError('accountNumber', $e->getMessage());

            return;
        }

        $this->showFormModal = false;
        $this->toast(__('Bank account saved.'));
    }

    public function render(): View
    {
        return view('finance::bank.accounts', [
            'bankAccounts' => BankAccount::where('school_id', $this->school->id)->orderBy('bank_name')->get(),
            'glAccounts' => $this->glAccountCandidates(),
        ]);
    }

    /**
     * @return Collection<int, Account>
     */
    private function glAccountCandidates(): Collection
    {
        return Account::where('school_id', $this->school->id)->where('is_active', true)->where('is_postable', true)->orderBy('code')->get();
    }
}
