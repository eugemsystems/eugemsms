<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Reports;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockBalance;
use Modules\Stores\Models\Store;

/**
 * `Stores\Reports\Valuation` (Book H1 FIN-09 §7, `inventory.report.view`).
 * Reads `stock_balances` — a verified cache, not a recomputation —
 * grouped by store and category, as at now.
 */
#[Title('Stock valuation')]
#[Layout('layouts.app')]
final class Valuation extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public ?int $storeId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('inventory.report.view');
    }

    public function render(): View
    {
        $balances = StockBalance::where('school_id', $this->school->id)
            ->when($this->storeId !== null, fn ($q) => $q->where('store_id', $this->storeId))
            ->where('quantity_on_hand', '>', 0)
            ->get();

        $items = InventoryItem::whereIn('id', $balances->pluck('item_id'))->with('category')->get()->keyBy('id');

        $byCategory = $balances->groupBy(function ($b) use ($items) {
            $category = $items[$b->item_id]->category;

            return $category !== null ? $category->name : __('Uncategorised');
        })
            ->map(fn ($group) => ['count' => $group->count(), 'value_minor' => (int) $group->sum('value_minor')]);

        return view('stores::reports.valuation', [
            'stores' => Store::where('school_id', $this->school->id)->orderBy('code')->get(),
            'byCategory' => $byCategory,
            'totalMinor' => (int) $balances->sum('value_minor'),
        ]);
    }
}
