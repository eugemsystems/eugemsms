<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Stores\Domain\Events\ExpiredStockWrittenOff;
use Modules\Stores\Models\StockLot;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\Store;

/**
 * ACT-WriteOffExpiredStock (Book H1 FIN-09 §7/BR-FIN-09-011/
 * AC-FIN-09-011). `StockCostingEngine::consume()` already refuses to
 * offer an expired lot for issue — this is the separate, approved path
 * that actually clears it off the books, to a wastage account, at the
 * lot's own cost (never today's replacement cost).
 */
final class WriteOffExpiredStockAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(
        int $lotId,
        float $quantity,
        int $wastageAccountId,
        int $approvedByUserId,
        int $academicYearId,
        int $termId,
        string $reason,
    ): StockMovement {
        $lot = StockLot::findOrFail($lotId);

        if (! $lot->isExpired()) {
            throw ValidationException::withMessages([
                'lotId' => "Lot #{$lot->id} has not expired — use a return or adjustment instead (BR-FIN-09-011).",
            ]);
        }

        if ($quantity <= 0.0 || $quantity > (float) $lot->quantity_remaining) {
            throw ValidationException::withMessages([
                'quantity' => "Write-off quantity must be positive and cannot exceed the {$lot->quantity_remaining} remaining on lot #{$lot->id}.",
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required to write off expired stock.',
            ]);
        }

        $store = Store::findOrFail($lot->store_id);
        $currency = Currency::from($lot->currency);
        $costMinor = (int) round($quantity * $lot->base_unit_cost_minor);

        return $this->transaction(function () use ($lot, $store, $currency, $quantity, $costMinor, $wastageAccountId, $approvedByUserId, $academicYearId, $termId, $reason): StockMovement {
            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $lot->school_id,
                academicYearId: $academicYearId,
                termId: $termId,
                journalType: 'STOCK_WRITE_OFF',
                narration: "Expired stock write-off — lot {$lot->lot_reference} ({$reason})",
                lines: [
                    new JournalLineData(accountId: $wastageAccountId, direction: 'DR', amount: Money::of($costMinor, $currency), costCentreId: $store->cost_centre_id),
                    new JournalLineData(accountId: $store->inventory_account_id, direction: 'CR', amount: Money::of($costMinor, $currency), costCentreId: $store->cost_centre_id),
                ],
                effectiveAt: now(),
                postedByUserId: $approvedByUserId,
                sourceType: 'stock_lot_write_off',
                sourceId: $lot->id,
            ));

            $newRemaining = (float) $lot->quantity_remaining - $quantity;
            $lot->update([
                'quantity_remaining' => $newRemaining,
                'is_depleted' => $newRemaining <= 0.0,
            ]);

            $onHandBefore = $this->currentOnHand($store->id, $lot->item_id);

            $movement = StockMovement::create([
                'school_id' => $lot->school_id,
                'academic_year_id' => $academicYearId,
                'term_id' => $termId,
                'store_id' => $store->id,
                'item_id' => $lot->item_id,
                'lot_id' => $lot->id,
                'movement_type' => 'write_off',
                'direction' => 'out',
                'quantity' => $quantity,
                'unit_cost_minor' => $lot->base_unit_cost_minor,
                'total_cost_minor' => $costMinor,
                'currency' => $currency->value,
                'base_total_minor' => $costMinor,
                'balance_after' => $onHandBefore - $quantity,
                'source_type' => 'stock_lot_write_off',
                'source_id' => $lot->id,
                'cost_centre_id' => $store->cost_centre_id,
                'expense_account_id' => $wastageAccountId,
                'journal_id' => $journal->id,
                'notes' => $reason,
                'performed_by' => $approvedByUserId,
                'occurred_at' => now(),
            ]);

            event(new ExpiredStockWrittenOff($lot, $quantity));

            return $movement;
        });
    }

    private function currentOnHand(int $storeId, int $itemId): float
    {
        $ins = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'in')->sum('quantity');
        $outs = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'out')->sum('quantity');

        return $ins - $outs;
    }
}
