<?php

declare(strict_types=1);

namespace Modules\Wallet\Livewire\SpendPoints;

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
use Modules\Stores\Models\Store;
use Modules\Wallet\Domain\Actions\CreateSpendPointAction;
use Modules\Wallet\Domain\DataObjects\CreateSpendPointData;
use Modules\Wallet\Models\SpendPoint;

/**
 * `Wallet\SpendPoints\Index` (Book H3 FIN-14 §2/§6, `wallet.manage`).
 * List + create, no `UpdateSpendPointAction` exists. Income account
 * and cost centre are picked from the full chart — rare, admin-config
 * actions, the same "no system key for income specifically" gap
 * PPL-02/PPL-03 already documented.
 */
#[Title('Spend points')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $pointType = 'tuckshop';

    public ?int $incomeAccountId = null;

    public ?int $costCentreId = null;

    public ?int $storeId = null;

    public bool $isFiscalisable = true;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('wallet.manage');
    }

    public function create(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:120'],
            'pointType' => ['required', 'in:tuckshop,canteen,stationery,printing,laundry,vending'],
            'incomeAccountId' => ['required', 'integer'],
            'costCentreId' => ['required', 'integer'],
        ]);

        app(CreateSpendPointAction::class)->execute(new CreateSpendPointData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            pointType: $this->pointType,
            incomeAccountId: (int) $this->incomeAccountId,
            costCentreId: (int) $this->costCentreId,
            storeId: $this->storeId,
            isFiscalisable: $this->isFiscalisable,
        ));

        $this->reset(['code', 'name']);
        $this->toast(__('Spend point created.'));
    }

    public function render(): View
    {
        return view('wallet::spend-points.index', [
            'spendPoints' => SpendPoint::where('school_id', $this->school->id)->orderBy('code')->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('name')->get(),
            'stores' => Store::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
