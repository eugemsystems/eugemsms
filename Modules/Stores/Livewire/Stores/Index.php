<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Stores;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Domain\Actions\CreateStoreAction;
use Modules\Stores\Domain\DataObjects\CreateStoreData;
use Modules\Stores\Models\Store;

/**
 * `Stores\Stores\Index` (Book H1 FIN-09 §7, `inventory.store.manage`/
 * `.view`). No `UpdateStoreAction` exists in the domain layer — the
 * same create-only precedent every prior book has hit for its own
 * catalogue-shaped screens (ACA-01's subject catalogue, BRD-01's hostel
 * wings) — so this screen is list+create, matching `Behaviour\Categories`'
 * own shape exactly.
 */
#[Title('Stores')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $storeType = 'main';

    public ?int $costCentreId = null;

    public ?int $inventoryAccountId = null;

    public ?int $defaultExpenseAccountId = null;

    public string $costingMethod = 'fifo';

    public ?int $custodianStaffId = null;

    public ?string $location = null;

    public bool $requiresIssueApproval = false;

    public bool $allowsNegativeStock = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('inventory.store.view');
    }

    public function create(): void
    {
        $this->authorizePermission('inventory.store.manage');

        $this->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:120'],
            'storeType' => ['required', 'string'],
            'costCentreId' => ['required', 'integer'],
            'inventoryAccountId' => ['required', 'integer'],
            'defaultExpenseAccountId' => ['required', 'integer'],
        ]);

        try {
            app(CreateStoreAction::class)->execute(new CreateStoreData(
                schoolId: $this->school->id,
                code: $this->code,
                name: $this->name,
                storeType: $this->storeType,
                costCentreId: (int) $this->costCentreId,
                inventoryAccountId: (int) $this->inventoryAccountId,
                defaultExpenseAccountId: (int) $this->defaultExpenseAccountId,
                costingMethod: $this->costingMethod,
                custodianStaffId: $this->custodianStaffId,
                location: $this->location,
                requiresIssueApproval: $this->requiresIssueApproval,
                allowsNegativeStock: $this->allowsNegativeStock,
                createdByUserId: (int) auth()->id(),
            ));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->reset(['code', 'name', 'location', 'custodianStaffId', 'requiresIssueApproval', 'allowsNegativeStock']);
        $this->toast(__('Store created.'));
    }

    public function render(): View
    {
        return view('stores::stores.index', [
            'stores' => Store::where('school_id', $this->school->id)->orderBy('code')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
