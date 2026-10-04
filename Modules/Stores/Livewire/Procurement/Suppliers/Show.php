<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Procurement\Suppliers;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\ComputeSupplierAgingAction;
use Modules\Stores\Domain\Actions\RecalculateSupplierPerformanceAction;
use Modules\Stores\Models\PurchaseOrder;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierInvoice;
use Modules\Stores\Models\SupplierPayment;

/**
 * `Procurement\Suppliers\Show` (Book H1 FIN-08 §7, `procurement.supplier.view`).
 * Orders, invoices, payments, aging, performance, and a link out to
 * `Suppliers\Clearances` — the per-supplier read side. "Recalculate
 * performance" runs `RecalculateSupplierPerformanceAction` on demand;
 * no scheduled command exists for it yet, matching every other
 * on-demand check this book defers.
 */
#[Title('Supplier')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Supplier $supplier;

    public function mount(School $school, Supplier $supplier): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('procurement.supplier.view');
        $this->supplier = $supplier;
    }

    public function recalculatePerformance(): void
    {
        $this->supplier = app(RecalculateSupplierPerformanceAction::class)->execute($this->supplier->id);
        $this->toast(__('Performance recalculated.'));
    }

    public function render(): View
    {
        return view('stores::procurement.suppliers.show', [
            'orders' => PurchaseOrder::where('supplier_id', $this->supplier->id)->orderByDesc('id')->limit(25)->get(),
            'invoices' => SupplierInvoice::where('supplier_id', $this->supplier->id)->orderByDesc('id')->limit(25)->get(),
            'payments' => SupplierPayment::where('supplier_id', $this->supplier->id)->orderByDesc('id')->limit(25)->get(),
            'aging' => app(ComputeSupplierAgingAction::class)->execute($this->supplier->id),
        ]);
    }
}
