<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Stores\Domain\DataObjects\ReceiveStockData;
use Modules\Stores\Domain\Events\StockReceived;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockLot;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\Store;

/**
 * ACT-ReceiveStock (Book H1 FIN-09 §2/BR-FIN-09-003/009/013/026). The
 * shared receipt primitive: opening stock import, a direct receipt, a
 * `FIN-08` GRN line, and `OPS-03`'s harvest/production-in all end up
 * here with a different `contraAccountId`/`sourceType` — this action
 * never assumes what the credit side is. Every receipt posts a real
 * journal in the same transaction as the lot it creates
 * (BR-FIN-09-003) — `stock_movements.journal_id` is populated from the
 * start, never patched in afterward, since the row is append-only.
 */
final class ReceiveStockAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(ReceiveStockData $data): StockLot
    {
        $store = Store::findOrFail($data->storeId);
        $item = InventoryItem::findOrFail($data->itemId);

        if ($item->requires_batch_tracking && ($data->batchNumber === null || trim($data->batchNumber) === '')) {
            throw ValidationException::withMessages([
                'batchNumber' => "{$item->name} requires batch tracking — a batch number is mandatory on receipt (BR-FIN-09-009).",
            ]);
        }

        $baseUnitCostMinor = $data->baseUnitCostMinor ?? $data->unitCostMinor;
        $totalCostMinor = (int) round($data->quantity * $data->unitCostMinor);
        $baseTotalMinor = (int) round($data->quantity * $baseUnitCostMinor);
        $currency = Currency::from($data->currency);

        return $this->transaction(function () use ($data, $store, $item, $baseUnitCostMinor, $totalCostMinor, $baseTotalMinor, $currency): StockLot {
            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $data->schoolId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                journalType: 'STOCK_RECEIPT',
                narration: "Stock receipt — {$item->name} into {$store->name}",
                lines: [
                    new JournalLineData(
                        accountId: $store->inventory_account_id,
                        direction: 'DR',
                        amount: Money::of($baseTotalMinor, $currency),
                        costCentreId: $store->cost_centre_id,
                        narration: "Receipt: {$item->name}",
                    ),
                    new JournalLineData(
                        accountId: $data->contraAccountId,
                        direction: 'CR',
                        amount: Money::of($baseTotalMinor, $currency),
                    ),
                ],
                effectiveAt: $data->receivedOn,
                postedByUserId: $data->performedByUserId,
                sourceType: $data->sourceType,
                sourceId: $data->sourceId,
            ));

            $lot = StockLot::create([
                'school_id' => $data->schoolId,
                'store_id' => $store->id,
                'item_id' => $item->id,
                'lot_reference' => $data->lotReference ?? (string) Str::ulid(),
                'batch_number' => $data->batchNumber,
                'received_on' => $data->receivedOn->toDateString(),
                'expiry_date' => $data->expiryDate?->toDateString(),
                'quantity_received' => $data->quantity,
                'quantity_remaining' => $data->quantity,
                'unit_cost_minor' => $data->unitCostMinor,
                'currency' => $data->currency,
                'base_unit_cost_minor' => $baseUnitCostMinor,
                'exchange_rate_id' => $data->exchangeRateId,
                'source_type' => $data->sourceType,
                'source_id' => $data->sourceId,
                'is_depleted' => false,
            ]);

            $onHandBefore = $this->currentOnHand($store->id, $item->id);

            StockMovement::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'store_id' => $store->id,
                'item_id' => $item->id,
                'lot_id' => $lot->id,
                'movement_type' => 'receipt',
                'direction' => 'in',
                'quantity' => $data->quantity,
                'unit_cost_minor' => $data->unitCostMinor,
                'total_cost_minor' => $totalCostMinor,
                'currency' => $data->currency,
                'base_total_minor' => $baseTotalMinor,
                'balance_after' => $onHandBefore + $data->quantity,
                'source_type' => $data->sourceType,
                'source_id' => $data->sourceId,
                'cost_centre_id' => $store->cost_centre_id,
                'journal_id' => $journal->id,
                'performed_by' => $data->performedByUserId,
                'occurred_at' => $data->receivedOn,
            ]);

            event(new StockReceived($lot));

            return $lot;
        });
    }

    private function currentOnHand(int $storeId, int $itemId): float
    {
        $ins = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'in')->sum('quantity');
        $outs = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'out')->sum('quantity');

        return $ins - $outs;
    }
}
