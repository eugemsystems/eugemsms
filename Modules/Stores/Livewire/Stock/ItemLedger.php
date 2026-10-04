<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Stock;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\Store;

/**
 * `Stores\Stock\ItemLedger` (Book H1 FIN-09 §7, `inventory.stock.view`).
 * Every row read directly from the append-only `stock_movements` table
 * (BR-FIN-09-001) — never from `stock_balances`. The journal column
 * links out to `Finance\Journals\Show` for the real GL drill-down
 * (BR-FIN-09-003: every value-changing movement carries a journal_id).
 */
#[Title('Item ledger')]
#[Layout('layouts.app')]
final class ItemLedger extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use WithPagination;

    public ?int $storeId = null;

    public ?int $itemId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('inventory.stock.view');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $movements = StockMovement::query()
            ->where('school_id', $this->school->id)
            ->when($this->storeId !== null, fn ($q) => $q->where('store_id', $this->storeId))
            ->when($this->itemId !== null, fn ($q) => $q->where('item_id', $this->itemId))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(25);

        return view('stores::stock.item-ledger', [
            'movements' => $movements,
            'stores' => Store::where('school_id', $this->school->id)->orderBy('code')->get(),
            'items' => InventoryItem::where('school_id', $this->school->id)->orderBy('name')->limit(300)->get(),
        ]);
    }
}
