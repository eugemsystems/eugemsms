<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Stores\Domain\DataObjects\DispatchStockTransferData;
use Modules\Stores\Domain\Exceptions\InsufficientStockException;
use Modules\Stores\Domain\Support\StockCostingEngine;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\StockTransfer;
use Modules\Stores\Models\StockTransferLine;
use Modules\Stores\Models\Store;

/**
 * ACT-DispatchStockTransfer (Book H1 FIN-09 §7/BR-FIN-09-018). The
 * source is decremented immediately (a FIFO consume, same as an
 * issue) so it stops counting toward the source's available balance
 * the moment it leaves — the destination only gains it back on
 * receipt (`ReceiveStockTransferAction`), so there is a real window
 * where the stock belongs to neither store's balance, exactly as
 * BR-FIN-09-018 requires. The value moves in the same single journal
 * (Dr destination inventory / Cr source inventory) posted here at
 * dispatch, on the assumption the transfer completes as dispatched;
 * `ReceiveStockTransferAction` never repeats it, only flags a
 * discrepancy and blocks completion if the counts don't match — the
 * value correction for a real discrepancy is a deliberate follow-up
 * step, not fabricated here.
 */
final class DispatchStockTransferAction extends Action
{
    public function __construct(
        private readonly StockCostingEngine $engine,
        private readonly PostJournalAction $postJournal,
        private readonly AllocateNumberAction $allocateNumber,
    ) {}

    public function execute(DispatchStockTransferData $data): StockTransfer
    {
        if ($data->fromStoreId === $data->toStoreId) {
            throw ValidationException::withMessages([
                'toStoreId' => 'A transfer must move stock between two different stores.',
            ]);
        }

        $fromStore = Store::findOrFail($data->fromStoreId);
        $toStore = Store::findOrFail($data->toStoreId);
        $currency = Currency::from('USD');

        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'stock_transfer',
            allocatedByUserId: $data->dispatchedByUserId,
            academicYearId: $data->academicYearId,
            termId: $data->termId,
        ));

        $dispatchedAt = Carbon::now();

        return $this->transaction(function () use ($data, $fromStore, $toStore, $currency, $number, $dispatchedAt): StockTransfer {
            $transfer = StockTransfer::create([
                'school_id' => $data->schoolId,
                'transfer_number' => $number->formatted_number,
                'from_store_id' => $fromStore->id,
                'to_store_id' => $toStore->id,
                'reason' => $data->reason,
                'status' => 'in_transit',
            ]);

            $pendingMovements = [];
            $totalValueMinor = 0;

            foreach ($data->items as $line) {
                $item = InventoryItem::findOrFail($line['itemId']);
                $quantity = (float) $line['quantity'];

                $result = $this->engine->consume($fromStore, $item, $quantity);

                if (! $result->isFullyConsumed()) {
                    throw InsufficientStockException::forItem($item->id, $result->shortfallQuantity);
                }

                $lineCostMinor = $result->totalCostMinor();
                $unitCostMinor = $quantity > 0 ? (int) round($lineCostMinor / $quantity) : 0;

                StockTransferLine::create([
                    'school_id' => $data->schoolId,
                    'transfer_id' => $transfer->id,
                    'item_id' => $item->id,
                    'quantity_dispatched' => $quantity,
                    'unit_cost_minor' => $unitCostMinor,
                    'line_cost_minor' => $lineCostMinor,
                    'currency' => $currency->value,
                ]);

                foreach ($result->consumptions as $consumption) {
                    $consumedUnitCost = $consumption->quantity > 0 ? (int) round($consumption->costMinor / $consumption->quantity) : 0;

                    $pendingMovements[] = [
                        'item' => $item,
                        'lot_id' => $consumption->lot->id,
                        'quantity' => $consumption->quantity,
                        'unit_cost_minor' => $consumedUnitCost,
                        'total_cost_minor' => $consumption->costMinor,
                    ];
                }

                $totalValueMinor += $lineCostMinor;
            }

            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $data->schoolId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                journalType: 'STOCK_TRANSFER',
                narration: "Stock transfer {$transfer->transfer_number} — {$fromStore->name} to {$toStore->name}",
                lines: [
                    new JournalLineData(accountId: $toStore->inventory_account_id, direction: 'DR', amount: Money::of($totalValueMinor, $currency), costCentreId: $toStore->cost_centre_id),
                    new JournalLineData(accountId: $fromStore->inventory_account_id, direction: 'CR', amount: Money::of($totalValueMinor, $currency), costCentreId: $fromStore->cost_centre_id),
                ],
                effectiveAt: $dispatchedAt,
                postedByUserId: $data->dispatchedByUserId,
                sourceType: 'stock_transfer',
                sourceId: $transfer->id,
            ));

            foreach ($pendingMovements as $pending) {
                $onHandBefore = $this->currentOnHand($fromStore->id, $pending['item']->id);

                StockMovement::create([
                    'school_id' => $data->schoolId,
                    'academic_year_id' => $data->academicYearId,
                    'term_id' => $data->termId,
                    'store_id' => $fromStore->id,
                    'item_id' => $pending['item']->id,
                    'lot_id' => $pending['lot_id'],
                    'movement_type' => 'transfer_out',
                    'direction' => 'out',
                    'quantity' => $pending['quantity'],
                    'unit_cost_minor' => $pending['unit_cost_minor'],
                    'total_cost_minor' => $pending['total_cost_minor'],
                    'currency' => $currency->value,
                    'base_total_minor' => $pending['total_cost_minor'],
                    'balance_after' => $onHandBefore - $pending['quantity'],
                    'source_type' => 'stock_transfer',
                    'source_id' => $transfer->id,
                    'cost_centre_id' => $fromStore->cost_centre_id,
                    'journal_id' => $journal->id,
                    'performed_by' => $data->dispatchedByUserId,
                    'occurred_at' => $dispatchedAt,
                ]);
            }

            $transfer->update([
                'journal_id' => $journal->id,
                'dispatched_by' => $data->dispatchedByUserId,
                'dispatched_at' => $dispatchedAt,
            ]);

            return $transfer;
        });
    }

    private function currentOnHand(int $storeId, int $itemId): float
    {
        $ins = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'in')->sum('quantity');
        $outs = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'out')->sum('quantity');

        return $ins - $outs;
    }
}
