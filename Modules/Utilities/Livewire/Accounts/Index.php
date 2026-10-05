<?php

declare(strict_types=1);

namespace Modules\Utilities\Livewire\Accounts;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Utilities\Domain\Actions\CreateUtilityAccountAction;
use Modules\Utilities\Domain\DataObjects\CreateUtilityAccountData;
use Modules\Utilities\Models\UtilityAccount;

/**
 * `Accounts\Index` (Book H2 OPS-04 §6, `utilities.manage`). Utility
 * account register — provider, tariff code, and the cost centre and
 * expense account every meter's own consumption expense eventually
 * posts against.
 */
#[Title('Utility accounts')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $utilityType = 'electricity';

    public string $provider = '';

    public string $accountNumber = '';

    public ?string $tariffCode = null;

    public string $billingMode = 'prepaid';

    public ?int $costCentreId = null;

    public ?int $expenseAccountId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('utilities.manage');
    }

    public function create(): void
    {
        $this->validate([
            'utilityType' => ['required', 'string'],
            'provider' => ['required', 'string'],
            'accountNumber' => ['required', 'string'],
            'billingMode' => ['required', 'string'],
            'costCentreId' => ['required', 'integer'],
            'expenseAccountId' => ['required', 'integer'],
        ]);

        app(CreateUtilityAccountAction::class)->execute(new CreateUtilityAccountData(
            schoolId: $this->school->id,
            utilityType: $this->utilityType,
            provider: $this->provider,
            accountNumber: $this->accountNumber,
            billingMode: $this->billingMode,
            costCentreId: (int) $this->costCentreId,
            expenseAccountId: (int) $this->expenseAccountId,
            tariffCode: $this->tariffCode !== '' ? $this->tariffCode : null,
        ));

        $this->reset(['provider', 'accountNumber', 'tariffCode']);
        $this->toast(__('Utility account created.'));
    }

    public function render(): View
    {
        return view('utilities::accounts.index', [
            'accounts' => UtilityAccount::where('school_id', $this->school->id)->orderBy('utility_type')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
            'accountsList' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
