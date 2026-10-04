<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Procurement\Suppliers;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Stores\Domain\Actions\ApproveSupplierAction;
use Modules\Stores\Domain\Actions\BlacklistSupplierAction;
use Modules\Stores\Domain\Actions\CreateSupplierAction;
use Modules\Stores\Domain\DataObjects\CreateSupplierData;
use Modules\Stores\Models\Supplier;

/**
 * `Procurement\Suppliers\Index` (Book H1 FIN-08 §7, `procurement.supplier.view`/
 * `.manage`/`.approve` ⚠). A supplier created here can never place or
 * receive an order the same session — `CreateSupplierAction` always
 * starts it `pending_approval` (BR-FIN-08-001), and `ApproveSupplierAction`
 * itself refuses the creator approving their own row.
 */
#[Title('Suppliers')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $supplierType = 'company';

    public string $preferredCurrency = 'USD';

    public ?string $vatNumber = null;

    public bool $isVatRegistered = false;

    public ?string $bpNumber = null;

    public ?int $controlAccountId = null;

    public string $search = '';

    /** @var array<int, string> supplierId => blacklist reason */
    public array $blacklistReasons = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('procurement.supplier.view');
    }

    public function create(): void
    {
        $this->authorizePermission('procurement.supplier.manage');

        $this->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:200'],
            'preferredCurrency' => ['required', 'size:3'],
            'controlAccountId' => ['required', 'integer'],
        ]);

        try {
            app(CreateSupplierAction::class)->execute(new CreateSupplierData(
                schoolId: $this->school->id,
                code: $this->code,
                name: $this->name,
                supplierType: $this->supplierType,
                preferredCurrency: $this->preferredCurrency,
                createdByUserId: (int) auth()->id(),
                vatNumber: $this->vatNumber,
                isVatRegistered: $this->isVatRegistered,
                bpNumber: $this->bpNumber,
                controlAccountId: $this->controlAccountId,
            ));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->reset(['code', 'name', 'vatNumber', 'isVatRegistered', 'bpNumber']);
        $this->toast(__('Supplier created, pending approval.'));
    }

    public function approve(int $supplierId): void
    {
        $this->authorizePermission('procurement.supplier.approve');

        try {
            app(ApproveSupplierAction::class)->execute($supplierId, (int) auth()->id());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Supplier approved.'));
    }

    public function blacklist(int $supplierId): void
    {
        $this->authorizePermission('procurement.supplier.manage');

        $reason = $this->blacklistReasons[$supplierId] ?? '';

        try {
            app(BlacklistSupplierAction::class)->execute($supplierId, $reason);
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        }

        $this->toast(__('Supplier blacklisted.'));
    }

    public function render(): View
    {
        return view('stores::procurement.suppliers.index', [
            'suppliers' => Supplier::where('school_id', $this->school->id)
                ->when($this->search !== '', fn ($q) => $q->where(fn ($q2) => $q2->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%")))
                ->orderBy('name')
                ->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->where('is_control_account', true)->orderBy('code')->get(),
        ]);
    }
}
