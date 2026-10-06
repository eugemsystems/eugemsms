<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Procurement\Orders;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Domain\Actions\ApprovePurchaseOrderAction;
use Modules\Stores\Domain\Actions\CancelPurchaseOrderAction;
use Modules\Stores\Domain\Actions\CheckBudgetAvailabilityAction;
use Modules\Stores\Domain\Actions\ClosePurchaseOrderShortAction;
use Modules\Stores\Domain\Actions\CreatePurchaseOrderAction;
use Modules\Stores\Domain\DataObjects\CreatePurchaseOrderData;
use Modules\Stores\Models\AssetCategory;
use Modules\Stores\Models\BudgetLine;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\PurchaseOrder;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\Supplier;

/**
 * `Procurement\Orders\Index` (Book H1 FIN-08 §7, `procurement.order.view`/
 * `.create`/`.approve` ⚠). Folds the spec's separate "Create order"
 * screen in here — list, create, approve, cancel, and close-short all
 * on one screen, the same fold `Behaviour\Categories` uses for its own
 * catalogue. The budget-impact preview reads `FIN-11`'s own
 * `CheckBudgetAvailabilityAction` live as the line total changes;
 * `ApprovePurchaseOrderAction` is what actually commits it
 * (BR-FIN-08-010/FIN-11 BR-FIN-11-004).
 */
#[Title('Purchase orders')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $supplierId = null;

    public ?int $costCentreId = null;

    public ?int $budgetLineId = null;

    public string $orderDate;

    /** @var array<int, array{item_id: string, description: string, quantity_ordered: string, unit: string, unit_price_minor: string, tax_rate_percent: string, tax_category: string, expense_account_id: string, is_capital: bool, asset_category_id: string, store_id: string}> */
    public array $lines = [];

    /** @var array<int, string> purchaseOrderId => reason */
    public array $cancelReasons = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('procurement.order.view');
        $this->orderDate = now()->toDateString();
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'item_id' => '', 'description' => '', 'quantity_ordered' => '', 'unit' => 'each',
            'unit_price_minor' => '', 'tax_rate_percent' => '0', 'tax_category' => 'standard',
            'expense_account_id' => '', 'is_capital' => false, 'asset_category_id' => '', 'store_id' => '',
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function estimatedTotalMinor(): int
    {
        $total = 0;

        foreach ($this->lines as $line) {
            $lineTotal = (float) ($line['quantity_ordered'] ?: 0) * (int) ($line['unit_price_minor'] ?: 0);
            $total += (int) round($lineTotal * (1 + (float) ($line['tax_rate_percent'] ?: 0) / 100));
        }

        return $total;
    }

    public function budgetAvailableMinor(): ?int
    {
        if ($this->budgetLineId === null) {
            return null;
        }

        return app(CheckBudgetAvailabilityAction::class)->execute($this->budgetLineId, $this->estimatedTotalMinor())->availableMinor;
    }

    public function create(): void
    {
        $this->authorizePermission('procurement.order.create');

        $this->validate([
            'supplierId' => ['required', 'integer'],
            'costCentreId' => ['required', 'integer'],
            'orderDate' => ['required', 'date'],
            'lines' => ['array', 'min:1'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.quantity_ordered' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price_minor' => ['required', 'integer', 'min:0'],
        ]);

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->toast(__('No current academic year/term is set.'), 'danger');

            return;
        }

        try {
            app(CreatePurchaseOrderAction::class)->execute(new CreatePurchaseOrderData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                supplierId: (int) $this->supplierId,
                costCentreId: (int) $this->costCentreId,
                orderDate: Carbon::parse($this->orderDate),
                currency: 'USD',
                lines: collect($this->lines)->map(fn (array $l): array => [
                    'itemId' => $l['item_id'] !== '' ? (int) $l['item_id'] : null,
                    'description' => $l['description'],
                    'quantityOrdered' => (float) $l['quantity_ordered'],
                    'unit' => $l['unit'],
                    'unitPriceMinor' => (int) $l['unit_price_minor'],
                    'taxRatePercent' => (float) $l['tax_rate_percent'],
                    'taxCategory' => $l['tax_category'],
                    'expenseAccountId' => $l['expense_account_id'] !== '' ? (int) $l['expense_account_id'] : null,
                    'isCapital' => (bool) $l['is_capital'],
                    'assetCategoryId' => $l['asset_category_id'] !== '' ? (int) $l['asset_category_id'] : null,
                    'storeId' => $l['store_id'] !== '' ? (int) $l['store_id'] : null,
                ])->all(),
                createdByUserId: (int) auth()->id(),
                budgetLineId: $this->budgetLineId,
            ));
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        }

        $this->reset(['lines', 'budgetLineId']);
        $this->addLine();
        $this->toast(__('Purchase order created, pending approval.'));
    }

    public function approve(int $purchaseOrderId): void
    {
        $this->authorizePermission('procurement.order.approve');

        try {
            app(ApprovePurchaseOrderAction::class)->execute($purchaseOrderId, (int) auth()->id());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Order approved — budget committed.'));
    }

    public function cancel(int $purchaseOrderId): void
    {
        $this->authorizePermission('procurement.order.approve');

        $reason = $this->cancelReasons[$purchaseOrderId] ?? '';

        try {
            app(CancelPurchaseOrderAction::class)->execute($purchaseOrderId, $reason, (int) auth()->id());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Order cancelled.'));
    }

    public function closeShort(int $purchaseOrderId): void
    {
        $this->authorizePermission('procurement.order.approve');

        $reason = $this->cancelReasons[$purchaseOrderId] ?? __('Closed short — balance will not be delivered.');

        try {
            app(ClosePurchaseOrderShortAction::class)->execute($purchaseOrderId, $reason, (int) auth()->id());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Order closed short — residual commitment released.'));
    }

    public function render(): View
    {
        return view('stores::procurement.orders.index', [
            'orders' => PurchaseOrder::with('supplier')->where('school_id', $this->school->id)->orderByDesc('id')->limit(50)->get(),
            'suppliers' => Supplier::where('school_id', $this->school->id)->where('status', 'active')->orderBy('name')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
            'budgetLines' => BudgetLine::where('school_id', $this->school->id)->orderBy('id')->limit(200)->get(),
            'items' => InventoryItem::where('school_id', $this->school->id)->orderBy('name')->limit(300)->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
            'assetCategories' => AssetCategory::where('school_id', $this->school->id)->orderBy('name')->get(),
            'stores' => Store::where('school_id', $this->school->id)->orderBy('code')->get(),
            'estimatedTotalMinor' => $this->estimatedTotalMinor(),
            'budgetAvailableMinor' => $this->budgetAvailableMinor(),
        ]);
    }
}
