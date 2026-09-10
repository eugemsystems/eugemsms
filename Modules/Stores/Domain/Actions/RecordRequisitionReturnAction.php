<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Stores\Domain\Events\StockReturned;
use Modules\Stores\Models\StockLot;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreRequisition;
use Modules\Stores\Models\StoreRequisitionLine;

/**
 * ACT-RecordRequisitionReturn (Book H1 FIN-09 §5/BR-FIN-09-008/
 * AC-FIN-09-003). Reverses at the ORIGINAL issue's own lot costs, in
 * the same order those lots were consumed — never at today's cost.
 * Returned quantity credits back to the lots it came from, so a
 * depleted lot can become available again.
 */
final class RecordRequisitionReturnAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(int $requisitionId, int $itemId, float $returnQuantity, int $receivedByUserId, ?CarbonInterface $returnedAt = null): StoreRequisitionLine
    {
        $requisition = StoreRequisition::findOrFail($requisitionId);
        $line = StoreRequisitionLine::where('requisition_id', $requisitionId)->where('item_id', $itemId)->firstOrFail();
        $store = Store::findOrFail($requisition->store_id);
        $returnedAt ??= Carbon::now();

        $issueMovements = StockMovement::query()
            ->where('source_type', 'store_requisition')
            ->where('source_id', $requisitionId)
            ->where('item_id', $itemId)
            ->where('movement_type', 'issue')
            ->orderBy('id')
            ->get();

        $remaining = $returnQuantity;
        $reversals = [];

        foreach ($issueMovements as $issued) {
            if ($remaining <= 0.0) {
                break;
            }

            $take = min($remaining, (float) $issued->quantity);
            $cost = (int) round($take * $issued->unit_cost_minor);

            $reversals[] = [
                'lot_id' => $issued->lot_id,
                'quantity' => $take,
                'unit_cost_minor' => $issued->unit_cost_minor,
                'total_cost_minor' => $cost,
                'expense_account_id' => $issued->expense_account_id,
                'cost_centre_id' => $issued->cost_centre_id,
            ];

            $remaining -= $take;
        }

        if ($remaining > 0.0) {
            throw ValidationException::withMessages([
                'returnQuantity' => "Cannot return {$returnQuantity} — only ".($returnQuantity - $remaining)." was ever issued for item #{$itemId} on this requisition.",
            ]);
        }

        $totalReversedMinor = (int) array_sum(array_column($reversals, 'total_cost_minor'));
        $currency = Currency::from($requisition->currency);
        $first = $reversals[0];

        return $this->transaction(function () use ($requisition, $store, $line, $reversals, $totalReversedMinor, $currency, $first, $returnQuantity, $returnedAt, $receivedByUserId): StoreRequisitionLine {
            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $requisition->school_id,
                academicYearId: $requisition->academic_year_id,
                termId: $requisition->term_id,
                journalType: 'STOCK_RETURN',
                narration: "Store return — requisition {$requisition->requisition_number}",
                lines: [
                    new JournalLineData(
                        accountId: $store->inventory_account_id,
                        direction: 'DR',
                        amount: Money::of($totalReversedMinor, $currency),
                        costCentreId: $store->cost_centre_id,
                    ),
                    new JournalLineData(
                        accountId: $first['expense_account_id'] ?? $store->default_expense_account_id,
                        direction: 'CR',
                        amount: Money::of($totalReversedMinor, $currency),
                        costCentreId: $first['cost_centre_id'],
                    ),
                ],
                effectiveAt: $returnedAt,
                postedByUserId: $receivedByUserId,
                sourceType: 'store_requisition',
                sourceId: $requisition->id,
            ));

            foreach ($reversals as $reversal) {
                if ($reversal['lot_id'] !== null) {
                    $lot = StockLot::find($reversal['lot_id']);
                    $lot?->update([
                        'quantity_remaining' => (float) $lot->quantity_remaining + $reversal['quantity'],
                        'is_depleted' => false,
                    ]);
                }

                $onHandBefore = $this->currentOnHand($store->id, $line->item_id);

                StockMovement::create([
                    'school_id' => $requisition->school_id,
                    'academic_year_id' => $requisition->academic_year_id,
                    'term_id' => $requisition->term_id,
                    'store_id' => $store->id,
                    'item_id' => $line->item_id,
                    'lot_id' => $reversal['lot_id'],
                    'movement_type' => 'return',
                    'direction' => 'in',
                    'quantity' => $reversal['quantity'],
                    'unit_cost_minor' => $reversal['unit_cost_minor'],
                    'total_cost_minor' => $reversal['total_cost_minor'],
                    'currency' => $requisition->currency,
                    'base_total_minor' => $reversal['total_cost_minor'],
                    'balance_after' => $onHandBefore + $reversal['quantity'],
                    'source_type' => 'store_requisition',
                    'source_id' => $requisition->id,
                    'cost_centre_id' => $reversal['cost_centre_id'],
                    'expense_account_id' => $reversal['expense_account_id'],
                    'journal_id' => $journal->id,
                    'performed_by' => $receivedByUserId,
                    'occurred_at' => $returnedAt,
                ]);
            }

            $line->update([
                'quantity_returned' => (float) ($line->quantity_returned ?? 0) + $returnQuantity,
            ]);

            event(new StockReturned($line));

            return $line;
        });
    }

    private function currentOnHand(int $storeId, int $itemId): float
    {
        $ins = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'in')->sum('quantity');
        $outs = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'out')->sum('quantity');

        return $ins - $outs;
    }
}
