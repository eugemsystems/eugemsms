<?php

declare(strict_types=1);

namespace Modules\Farm\Livewire\Sales;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Farm\Domain\Actions\RecordFarmSaleAction;
use Modules\Farm\Domain\DataObjects\RecordFarmSaleData;
use Modules\Farm\Models\FarmSale;
use Modules\Farm\Models\ProductionUnit;
use Modules\Finance\Models\Account;

/**
 * `Sales\Index` (Book H2 OPS-03 §5/BR-OPS-03-016, `farm.sales.manage`).
 * `fiscal_receipt_id` stays null on every row this screen creates —
 * `FIN-13` (Book H3) doesn't exist yet in this codebase, a documented
 * boundary `RecordFarmSaleAction`'s own docblock and
 * `FarmServiceProvider`'s class docblock both already name. The
 * spec's own AC-OPS-03-006 ("fiscalised through FIN-13") is therefore
 * not literally true today — recorded here rather than silently
 * improvised past.
 */
#[Title('Farm sales')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $productionUnitId = null;

    public string $buyerName = '';

    public ?string $buyerContact = null;

    public string $itemDescription = '';

    public string $quantity = '';

    public string $unit = 'kg';

    public string $unitPriceMinor = '';

    public ?int $cashAccountId = null;

    public ?int $salesIncomeAccountId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('farm.sales.manage');
    }

    public function record(): void
    {
        $this->validate([
            'productionUnitId' => ['required', 'integer'],
            'buyerName' => ['required', 'string'],
            'itemDescription' => ['required', 'string'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unitPriceMinor' => ['required', 'integer', 'gt:0'],
            'cashAccountId' => ['required', 'integer'],
            'salesIncomeAccountId' => ['required', 'integer'],
        ]);

        app(RecordFarmSaleAction::class)->execute(new RecordFarmSaleData(
            schoolId: $this->school->id,
            academicYearId: (int) SessionContext::yearId(),
            termId: (int) SessionContext::termId(),
            productionUnitId: (int) $this->productionUnitId,
            saleDate: Carbon::now(),
            buyerName: $this->buyerName,
            itemDescription: $this->itemDescription,
            quantity: (float) $this->quantity,
            unit: $this->unit,
            unitPriceMinor: (int) $this->unitPriceMinor,
            currency: $this->school->base_currency,
            cashAccountId: (int) $this->cashAccountId,
            salesIncomeAccountId: (int) $this->salesIncomeAccountId,
            performedByUserId: (int) auth()->id(),
            buyerContact: $this->buyerContact,
        ));

        $this->reset(['buyerName', 'buyerContact', 'itemDescription', 'quantity', 'unitPriceMinor']);
        $this->toast(__('Farm sale recorded.'));
    }

    public function render(): View
    {
        return view('farm::sales.index', [
            'sales' => FarmSale::where('school_id', $this->school->id)->orderByDesc('id')->limit(50)->get(),
            'units' => ProductionUnit::where('school_id', $this->school->id)->orderBy('name')->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
