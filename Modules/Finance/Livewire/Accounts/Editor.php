<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Accounts;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CreateAccountAction;
use Modules\Finance\Domain\Actions\UpdateAccountAction;
use Modules\Finance\Domain\DataObjects\CreateAccountData;
use Modules\Finance\Domain\DataObjects\UpdateAccountData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\AccountType;

/**
 * `Finance\Accounts\Editor` (Book B FIN-01 §8, `finance.account.manage`)
 * — create or edit an account. `code`, `accountTypeCode`, and
 * `system_key` are only settable on create (BR-FIN-01-019: a system
 * account's identity fields never change; `UpdateAccountAction` itself
 * doesn't even accept them — see that DTO's own docblock).
 */
#[Title('Account editor')]
#[Layout('layouts.app')]
final class Editor extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $editingAccountId = null;

    public string $accountTypeCode = 'ASSET';

    public string $code = '';

    public string $name = '';

    public ?string $description = null;

    public ?int $parentId = null;

    public bool $isPostable = true;

    public bool $isControlAccount = false;

    public ?string $subledgerType = null;

    public bool $requiresCostCentre = false;

    public ?string $currency = null;

    public function mount(School $school, ?Account $account = null): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.account.manage');

        if ($account !== null) {
            $this->editingAccountId = $account->id;
            $this->accountTypeCode = $account->accountType->code;
            $this->code = $account->code;
            $this->name = $account->name;
            $this->description = $account->description;
            $this->parentId = $account->parent_id;
            $this->isPostable = $account->is_postable;
            $this->isControlAccount = $account->is_control_account;
            $this->subledgerType = $account->subledger_type;
            $this->requiresCostCentre = $account->requires_cost_centre;
            $this->currency = $account->currency;
        }
    }

    public function save(): void
    {
        $this->validate([
            'accountTypeCode' => ['required', 'in:ASSET,LIABILITY,EQUITY,INCOME,EXPENSE'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'parentId' => ['nullable', 'integer'],
            'subledgerType' => ['nullable', 'in:learner,guardian,supplier,staff'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        try {
            if ($this->editingAccountId !== null) {
                app(UpdateAccountAction::class)->execute(new UpdateAccountData(
                    accountId: $this->editingAccountId,
                    updatedByUserId: (int) Auth::id(),
                    name: $this->name,
                    description: $this->description,
                    isPostable: $this->isPostable,
                    requiresCostCentre: $this->requiresCostCentre,
                ));
            } else {
                app(CreateAccountAction::class)->execute(new CreateAccountData(
                    schoolId: $this->school->id,
                    accountTypeCode: $this->accountTypeCode,
                    code: $this->code,
                    name: $this->name,
                    createdByUserId: (int) Auth::id(),
                    description: $this->description,
                    parentId: $this->parentId,
                    isPostable: $this->isPostable,
                    isControlAccount: $this->isControlAccount,
                    subledgerType: $this->subledgerType !== '' ? $this->subledgerType : null,
                    currency: $this->currency !== '' ? $this->currency : null,
                    requiresCostCentre: $this->requiresCostCentre,
                ));
            }
        } catch (DomainException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        $this->redirectRoute('finance.accounts.tree', ['school' => $this->school], navigate: true);
    }

    public function render(): View
    {
        return view('finance::accounts.editor', [
            'accountTypes' => AccountType::orderBy('sort_order')->get(),
            'parentCandidates' => Account::query()
                ->where('is_postable', false)
                ->when($this->editingAccountId !== null, fn ($q) => $q->where('id', '!=', $this->editingAccountId))
                ->orderBy('code')
                ->get(),
        ]);
    }
}
