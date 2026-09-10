<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Stores\Domain\Events\StockTransferred;
use Modules\Stores\Domain\Events\TransferDiscrepancy;
use Modules\Stores\Models\StockLot;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\StockTransfer;
use Modules\Stores\Models\Store;

/**
 * ACT-ReceiveStockTransfer (Book H1 FIN-09 §7/BR-FIN-09-018/019). The
 * destination gets a brand-new lot at each line's own dispatched unit
 * cost (never a fresh valuation) — the same "receive at the original
 * cost basis" doctrine `RecordRequisitionReturnAction` uses. Any line
 * whose received quantity doesn't match what was dispatched sets the
 * whole transfer to `discrepancy` and fires an alert; it never
 * silently reconciles the gap, and never completes while one stands
 * (BR-FIN-09-019).
 */
final class ReceiveStockTransferAction extends Action
{
    /**
     * @param  array<int, float>  $receivedQuantitiesByLineId
     */
    public function execute(
        int $transferId,
        array $receivedQuantitiesByLineId,
        int $receivedByUserId,
        int $academicYearId,
        int $termId,
        ?string $discrepancyNote = null,
    ): StockTransfer {
        $transfer = StockTransfer::with('lines')->findOrFail($transferId);

        if ($transfer->status !== 'in_transit') {
            throw new InvalidStateTransitionException(
                "Transfer {$transfer->transfer_number} is not in transit and cannot be received.",
                ['stock_transfer_id' => $transfer->id, 'status' => $transfer->status],
            );
        }

        if (array_diff_key($receivedQuantitiesByLineId, $transfer->lines->keyBy('id')->all()) !== []) {
            throw ValidationException::withMessages([
                'receivedQuantitiesByLineId' => 'One or more lines do not belong to this transfer.',
            ]);
        }

        $toStore = Store::findOrFail($transfer->to_store_id);
        $receivedAt = Carbon::now();

        return $this->transaction(function () use ($transfer, $toStore, $receivedQuantitiesByLineId, $receivedByUserId, $academicYearId, $termId, $discrepancyNote, $receivedAt): StockTransfer {
            $hasDiscrepancy = false;

            foreach ($transfer->lines as $line) {
                $receivedQuantity = (float) ($receivedQuantitiesByLineId[$line->id] ?? $line->quantity_dispatched);

                if (abs($receivedQuantity - (float) $line->quantity_dispatched) > 0.0001) {
                    $hasDiscrepancy = true;
                }

                $lot = StockLot::create([
                    'school_id' => $transfer->school_id,
                    'store_id' => $toStore->id,
                    'item_id' => $line->item_id,
                    'lot_reference' => "TRF-{$transfer->transfer_number}-".(string) Str::ulid(),
                    'received_on' => $receivedAt->toDateString(),
                    'quantity_received' => $receivedQuantity,
                    'quantity_remaining' => $receivedQuantity,
                    'unit_cost_minor' => $line->unit_cost_minor,
                    'currency' => $line->currency,
                    'base_unit_cost_minor' => $line->unit_cost_minor,
                    'source_type' => 'stock_transfer',
                    'source_id' => $transfer->id,
                    'is_depleted' => $receivedQuantity <= 0.0,
                ]);

                $onHandBefore = $this->currentOnHand($toStore->id, $line->item_id);

                StockMovement::create([
                    'school_id' => $transfer->school_id,
                    'academic_year_id' => $academicYearId,
                    'term_id' => $termId,
                    'store_id' => $toStore->id,
                    'item_id' => $line->item_id,
                    'lot_id' => $lot->id,
                    'movement_type' => 'transfer_in',
                    'direction' => 'in',
                    'quantity' => $receivedQuantity,
                    'unit_cost_minor' => $line->unit_cost_minor,
                    'total_cost_minor' => (int) round($receivedQuantity * $line->unit_cost_minor),
                    'currency' => $line->currency,
                    'base_total_minor' => (int) round($receivedQuantity * $line->unit_cost_minor),
                    'balance_after' => $onHandBefore + $receivedQuantity,
                    'source_type' => 'stock_transfer',
                    'source_id' => $transfer->id,
                    'cost_centre_id' => $toStore->cost_centre_id,
                    'journal_id' => $transfer->journal_id,
                    'performed_by' => $receivedByUserId,
                    'occurred_at' => $receivedAt,
                ]);

                $line->update(['quantity_received' => $receivedQuantity]);
            }

            $transfer->update([
                'status' => $hasDiscrepancy ? 'discrepancy' : 'received',
                'received_by' => $receivedByUserId,
                'received_at' => $receivedAt,
                'discrepancy_note' => $hasDiscrepancy ? ($discrepancyNote ?? 'Received quantity did not match dispatched quantity on one or more lines.') : null,
            ]);

            if ($hasDiscrepancy) {
                event(new TransferDiscrepancy($transfer));
            } else {
                event(new StockTransferred($transfer));
            }

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
