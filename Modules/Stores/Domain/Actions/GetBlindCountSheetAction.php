<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\DataObjects\BlindCountLine;
use Modules\Stores\Models\StockTakeLine;

/**
 * ACT-GetBlindCountSheet (Book H1 FIN-09 §7 ⭐/BR-FIN-09-014/
 * AC-FIN-09-005). Not a case of hiding `system_quantity` client-side —
 * this query never selects the column into the response at all, and
 * `BlindCountLine` has nowhere to put it even if it did.
 */
final class GetBlindCountSheetAction extends Action
{
    /**
     * @return Collection<int, BlindCountLine>
     */
    public function execute(int $stockTakeId): Collection
    {
        return StockTakeLine::query()
            ->with('item:id,name,base_unit')
            ->where('stock_take_id', $stockTakeId)
            ->get(['id', 'item_id'])
            ->map(fn (StockTakeLine $line): BlindCountLine => new BlindCountLine(
                lineId: $line->id,
                itemId: $line->item_id,
                itemName: $line->item->name,
                baseUnit: $line->item->base_unit,
            ));
    }
}
