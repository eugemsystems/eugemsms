<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Stores\Domain\DataObjects\CreateStockTakeData;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\StockTake;
use Modules\Stores\Models\StockTakeLine;
use Modules\Stores\Models\StoreItemSetting;

/**
 * ACT-CreateStockTake (Book H1 FIN-09 §7/BR-FIN-09-014/017 ⭐). Every
 * stocked item's `system_quantity` is captured NOW, computed live from
 * `stock_movements` (never the `stock_balances` cache), so a stale
 * cache can never leak into a count. A `full` take always includes
 * every high-risk item regardless of any cycle plan
 * (BR-FIN-09-017) — this pass has no cycle-subset selection yet, so
 * every take is effectively a full count of the store's stocked items.
 */
final class CreateStockTakeAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(CreateStockTakeData $data): StockTake
    {
        $scope = new ScopeChain(schoolId: $data->schoolId);
        $isBlind = $data->isBlindCount ?? (bool) $this->settings->get('inventory.stocktake_blind', $scope);

        $itemIds = StoreItemSetting::query()
            ->where('store_id', $data->storeId)
            ->where('is_stocked', true)
            ->pluck('item_id');

        return $this->transaction(function () use ($data, $isBlind, $itemIds): StockTake {
            $take = StockTake::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'store_id' => $data->storeId,
                'take_number' => 'ST/'.now()->format('Ymd').'/'.random_int(1000, 9999),
                'take_type' => $data->takeType,
                'scheduled_for' => $data->scheduledFor->toDateString(),
                'status' => 'counting',
                'is_blind_count' => $isBlind,
                'line_count' => $itemIds->count(),
            ]);

            foreach ($itemIds as $itemId) {
                StockTakeLine::create([
                    'school_id' => $data->schoolId,
                    'stock_take_id' => $take->id,
                    'item_id' => $itemId,
                    'system_quantity' => $this->currentOnHand($data->storeId, $itemId),
                    'requires_recount' => false,
                ]);
            }

            return $take;
        });
    }

    private function currentOnHand(int $storeId, int $itemId): float
    {
        $ins = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'in')->sum('quantity');
        $outs = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'out')->sum('quantity');

        return $ins - $outs;
    }
}
