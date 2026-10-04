<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Stock;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\CheckReorderLevelsAction;
use Modules\Stores\Domain\Actions\RebuildStockBalanceAction;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockBalance;
use Modules\Stores\Models\Store;

/**
 * `Stores\Stock\OnHand` (Book H1 FIN-09 §7, `inventory.stock.view`).
 * Reorder flags come from a live `CheckReorderLevelsAction` run against
 * the selected store, not a denormalised column — BR-FIN-09-024 is
 * read, never cached. "Rebuild" surfaces `RebuildStockBalanceAction`
 * directly per item: `stock_balances` is a cache only, and this is the
 * one approved path that writes to it (BR-FIN-09-002).
 */
#[Title('Stock on hand')]
#[Layout('layouts.app')]
final class OnHand extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $storeId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('inventory.stock.view');
    }

    public function rebuild(int $itemId): void
    {
        if ($this->storeId === null) {
            return;
        }

        app(RebuildStockBalanceAction::class)->execute($this->school->id, $this->storeId, $itemId);
        $this->toast(__('Balance rebuilt from source movements.'));
    }

    public function render(): View
    {
        $stores = Store::where('school_id', $this->school->id)->orderBy('code')->get();
        $this->storeId ??= $stores->first()?->id;

        $balances = collect();
        $reorderItemIds = collect();

        if ($this->storeId !== null) {
            $balances = StockBalance::where('school_id', $this->school->id)
                ->where('store_id', $this->storeId)
                ->where('quantity_on_hand', '>', 0)
                ->get()
                ->keyBy('item_id');

            $reorderItemIds = app(CheckReorderLevelsAction::class)->execute($this->storeId)->pluck('item_id');
        }

        /** @var Collection<int, InventoryItem> $items */
        $items = InventoryItem::whereIn('id', $balances->keys())->get()->keyBy('id');

        return view('stores::stock.on-hand', [
            'stores' => $stores,
            'balances' => $balances,
            'items' => $items,
            'reorderItemIds' => $reorderItemIds,
        ]);
    }
}
