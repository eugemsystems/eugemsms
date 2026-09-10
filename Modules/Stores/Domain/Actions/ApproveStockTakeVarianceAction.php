<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Stores\Domain\Events\StockAdjustmentPosted;
use Modules\Stores\Domain\Support\StockCostingEngine;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockLot;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\StockTake;
use Modules\Stores\Models\StockTakeLine;
use Modules\Stores\Models\Store;

/**
 * ACT-ApproveStockTakeVariance (Book H1 FIN-09 §7/BR-FIN-09-016/
 * AC-FIN-09-007). Refuses while any line still `requires_recount` —
 * variance is never silently absorbed (BR-FIN-09-016), and it is
 * never approved with an open recount outstanding either. Posts ONE
 * adjustment journal for the whole take, to the shrinkage account,
 * with lines aggregated the same "one journal, grouped" discipline as
 * `IssueStockAction`.
 */
final class ApproveStockTakeVarianceAction extends Action
{
    public function __construct(
        private readonly StockCostingEngine $engine,
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(int $stockTakeId, int $approvedByUserId, int $shrinkageAccountId, int $academicYearId): StockTake
    {
        $take = StockTake::with('lines')->findOrFail($stockTakeId);

        if ($take->lines->contains(fn (StockTakeLine $l): bool => $l->requires_recount)) {
            throw new InvalidStateTransitionException(
                "Stock take #{$take->id} has lines still requiring a recount — cannot approve (BR-FIN-09-015).",
                ['stock_take_id' => $take->id],
            );
        }

        $variantLines = $take->lines->filter(fn (StockTakeLine $l): bool => $this->finalVariance($l) !== 0.0);

        foreach ($variantLines as $line) {
            if (trim((string) $line->variance_reason) === '') {
                throw ValidationException::withMessages([
                    'variance_reason' => "Line #{$line->id} has a variance and requires a recorded reason before approval (BR-FIN-09-016).",
                ]);
            }
        }

        $store = Store::findOrFail($take->store_id);
        $currency = Currency::from('USD');

        return $this->transaction(function () use ($take, $store, $currency, $variantLines, $approvedByUserId, $shrinkageAccountId, $academicYearId): StockTake {
            $totalShrinkageMinor = 0;
            $movements = [];

            foreach ($variantLines as $line) {
                $item = InventoryItem::findOrFail($line->item_id);
                $variance = $this->finalVariance($line);

                if ($variance < 0) {
                    $result = $this->engine->consume($store, $item, abs($variance));
                    $costMinor = $result->totalCostMinor();

                    foreach ($result->consumptions as $consumption) {
                        $movements[] = [
                            'item_id' => $item->id, 'lot_id' => $consumption->lot->id, 'direction' => 'out',
                            'quantity' => $consumption->quantity, 'unit_cost_minor' => (int) round($consumption->costMinor / max($consumption->quantity, 0.0001)),
                            'total_cost_minor' => $consumption->costMinor, 'movement_type' => 'adjustment_down',
                        ];
                    }
                } else {
                    $unitCost = $this->engine->weightedAverageUnitCostMinor($store, $item) ?? (int) ($item->standard_cost_minor ?? 0);
                    $costMinor = (int) round($variance * $unitCost);

                    $lot = StockLot::create([
                        'school_id' => $take->school_id,
                        'store_id' => $store->id,
                        'item_id' => $item->id,
                        'lot_reference' => "ADJ-{$take->take_number}-{$item->id}",
                        'received_on' => now()->toDateString(),
                        'quantity_received' => $variance,
                        'quantity_remaining' => $variance,
                        'unit_cost_minor' => $unitCost,
                        'currency' => 'USD',
                        'base_unit_cost_minor' => $unitCost,
                        'source_type' => 'adjustment',
                        'source_id' => $take->id,
                        'is_depleted' => false,
                    ]);

                    $movements[] = [
                        'item_id' => $item->id, 'lot_id' => $lot->id, 'direction' => 'in',
                        'quantity' => $variance, 'unit_cost_minor' => $unitCost,
                        'total_cost_minor' => $costMinor, 'movement_type' => 'adjustment_up',
                    ];
                }

                $totalShrinkageMinor += $costMinor;
            }

            $inventoryDirection = $totalShrinkageMinor >= 0 ? 'CR' : 'DR';
            $shrinkageDirection = $totalShrinkageMinor >= 0 ? 'DR' : 'CR';
            $absAmount = Money::of(abs($totalShrinkageMinor), $currency);

            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $take->school_id,
                academicYearId: $academicYearId,
                termId: $take->term_id,
                journalType: 'STOCK_ADJUSTMENT',
                narration: "Stock take variance — {$take->take_number}",
                lines: [
                    new JournalLineData(accountId: $shrinkageAccountId, direction: $shrinkageDirection, amount: $absAmount, costCentreId: $store->cost_centre_id),
                    new JournalLineData(accountId: $store->inventory_account_id, direction: $inventoryDirection, amount: $absAmount, costCentreId: $store->cost_centre_id),
                ],
                effectiveAt: Carbon::now(),
                postedByUserId: $approvedByUserId,
                sourceType: 'stock_take',
                sourceId: $take->id,
            ));

            foreach ($movements as $m) {
                $onHandBefore = $this->currentOnHand($store->id, $m['item_id']);
                $delta = $m['direction'] === 'in' ? $m['quantity'] : -$m['quantity'];

                StockMovement::create([
                    'school_id' => $take->school_id,
                    'academic_year_id' => $academicYearId,
                    'term_id' => $take->term_id,
                    'store_id' => $store->id,
                    'item_id' => $m['item_id'],
                    'lot_id' => $m['lot_id'],
                    'movement_type' => $m['movement_type'],
                    'direction' => $m['direction'],
                    'quantity' => $m['quantity'],
                    'unit_cost_minor' => $m['unit_cost_minor'],
                    'total_cost_minor' => $m['total_cost_minor'],
                    'currency' => 'USD',
                    'base_total_minor' => $m['total_cost_minor'],
                    'balance_after' => $onHandBefore + $delta,
                    'source_type' => 'stock_take',
                    'source_id' => $take->id,
                    'cost_centre_id' => $store->cost_centre_id,
                    'expense_account_id' => $shrinkageAccountId,
                    'journal_id' => $journal->id,
                    'performed_by' => $approvedByUserId,
                    'occurred_at' => Carbon::now(),
                ]);
            }

            $take->update([
                'status' => 'posted',
                'approved_by' => $approvedByUserId,
                'total_variance_minor' => $totalShrinkageMinor,
                'variance_line_count' => $variantLines->count(),
                'journal_id' => $journal->id,
            ]);

            event(new StockAdjustmentPosted($take));

            return $take;
        });
    }

    private function finalVariance(StockTakeLine $line): float
    {
        $quantity = $line->recount_quantity ?? $line->counted_quantity;

        return $quantity === null ? 0.0 : (float) $quantity - (float) $line->system_quantity;
    }

    private function currentOnHand(int $storeId, int $itemId): float
    {
        $ins = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'in')->sum('quantity');
        $outs = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'out')->sum('quantity');

        return $ins - $outs;
    }
}
