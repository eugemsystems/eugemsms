<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Stores\Domain\Events\ItemCapitalisationDue;
use Modules\Stores\Domain\Events\NegativeStockIssued;
use Modules\Stores\Domain\Events\StockIssued;
use Modules\Stores\Domain\Exceptions\InsufficientStockException;
use Modules\Stores\Domain\Support\StockCostingEngine;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreRequisition;

/**
 * ACT-IssueStock (Book H1 FIN-09 §4 ⭐⭐/BR-FIN-09-004/005/006/007/009/
 * 010/011/021/AC-FIN-09-001/002/004/009/011/012). The heart of the
 * module. FIFO/expiry-first consumption locks every lot it touches
 * (`StockCostingEngine::consume()`, called from inside this action's
 * own transaction so the lock is held for the whole issue — two
 * concurrent issues can never consume the same layer,
 * AC-FIN-09-004). Expense lands on the REQUESTING cost centre
 * (BR-FIN-09-006), and exactly one journal posts per requisition with
 * lines aggregated by account and cost centre (BR-FIN-09-007) — a
 * forty-item kitchen requisition produces one readable journal, not
 * forty. A capitalisable item above threshold still expenses in this
 * pass (see `ItemCapitalisationDue`'s own docblock for why) rather
 * than fabricating a `FIN-10` asset account that doesn't exist yet.
 */
final class IssueStockAction extends Action
{
    public function __construct(
        private readonly StockCostingEngine $engine,
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(int $requisitionId, int $issuedByUserId, ?CarbonInterface $issuedAt = null): StoreRequisition
    {
        $requisition = StoreRequisition::with('lines')->findOrFail($requisitionId);
        $requisition->throwIfNotOpenForIssue();

        $issuedAt ??= Carbon::now();
        $store = Store::findOrFail($requisition->store_id);
        $currency = Currency::from($requisition->currency);

        return $this->transaction(function () use ($requisition, $store, $currency, $issuedByUserId, $issuedAt): StoreRequisition {
            /** @var array<string, array{accountId: int, costCentreId: int|null, amount: Money}> $expenseLines */
            $expenseLines = [];
            $pendingMovements = [];
            $totalCostMinor = 0;

            foreach ($requisition->lines as $line) {
                $item = InventoryItem::findOrFail($line->item_id);
                $quantity = (float) ($line->quantity_approved ?? $line->quantity_requested);

                $result = $this->engine->consume($store, $item, $quantity);
                $lineCostMinor = $result->totalCostMinor();

                foreach ($result->consumptions as $consumption) {
                    $unitCost = $consumption->quantity > 0 ? (int) round($consumption->costMinor / $consumption->quantity) : 0;

                    $pendingMovements[] = [
                        'item' => $item,
                        'lot_id' => $consumption->lot->id,
                        'quantity' => $consumption->quantity,
                        'unit_cost_minor' => $unitCost,
                        'total_cost_minor' => $consumption->costMinor,
                        'is_negative' => false,
                    ];
                }

                if ($result->shortfallQuantity > 0.0) {
                    if (! $store->allows_negative_stock) {
                        throw InsufficientStockException::forItem($item->id, $result->shortfallQuantity);
                    }

                    $lastCost = $this->engine->lastKnownUnitCostMinor($store, $item);
                    $shortfallCost = (int) round($result->shortfallQuantity * $lastCost);
                    $lineCostMinor += $shortfallCost;

                    $pendingMovements[] = [
                        'item' => $item,
                        'lot_id' => null,
                        'quantity' => $result->shortfallQuantity,
                        'unit_cost_minor' => $lastCost,
                        'total_cost_minor' => $shortfallCost,
                        'is_negative' => true,
                    ];
                }

                $expenseAccountId = $item->expense_account_id ?? $store->default_expense_account_id;
                $key = $expenseAccountId.'|'.$requisition->cost_centre_id;

                if (! isset($expenseLines[$key])) {
                    $expenseLines[$key] = [
                        'accountId' => $expenseAccountId,
                        'costCentreId' => $requisition->cost_centre_id,
                        'amount' => Money::zero($currency),
                    ];
                }

                $expenseLines[$key]['amount'] = $expenseLines[$key]['amount']->plus(Money::of($lineCostMinor, $currency));
                $totalCostMinor += $lineCostMinor;

                $line->update([
                    'quantity_issued' => $quantity,
                    'unit_cost_minor' => $quantity > 0 ? (int) round($lineCostMinor / $quantity) : null,
                    'line_cost_minor' => $lineCostMinor,
                ]);
            }

            $journalLines = array_values(array_map(
                fn (array $l): JournalLineData => new JournalLineData(
                    accountId: $l['accountId'],
                    direction: 'DR',
                    amount: $l['amount'],
                    costCentreId: $l['costCentreId'],
                ),
                $expenseLines,
            ));

            $journalLines[] = new JournalLineData(
                accountId: $store->inventory_account_id,
                direction: 'CR',
                amount: Money::of($totalCostMinor, $currency),
                costCentreId: $store->cost_centre_id,
            );

            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $requisition->school_id,
                academicYearId: $requisition->academic_year_id,
                termId: $requisition->term_id,
                journalType: 'STOCK_ISSUE',
                narration: "Store issue {$requisition->requisition_number} — {$requisition->purpose}",
                lines: $journalLines,
                effectiveAt: $issuedAt,
                postedByUserId: $issuedByUserId,
                sourceType: 'store_requisition',
                sourceId: $requisition->id,
            ));

            foreach ($pendingMovements as $pending) {
                $onHandBefore = $this->currentOnHand($store->id, $pending['item']->id);

                $movement = StockMovement::create([
                    'school_id' => $requisition->school_id,
                    'academic_year_id' => $requisition->academic_year_id,
                    'term_id' => $requisition->term_id,
                    'store_id' => $store->id,
                    'item_id' => $pending['item']->id,
                    'lot_id' => $pending['lot_id'],
                    'movement_type' => 'issue',
                    'direction' => 'out',
                    'quantity' => $pending['quantity'],
                    'unit_cost_minor' => $pending['unit_cost_minor'],
                    'total_cost_minor' => $pending['total_cost_minor'],
                    'currency' => $requisition->currency,
                    'base_total_minor' => $pending['total_cost_minor'],
                    'balance_after' => $onHandBefore - $pending['quantity'],
                    'source_type' => 'store_requisition',
                    'source_id' => $requisition->id,
                    'cost_centre_id' => $requisition->cost_centre_id,
                    'expense_account_id' => $pending['item']->expense_account_id ?? $store->default_expense_account_id,
                    'journal_id' => $journal->id,
                    'notes' => $pending['is_negative'] ? 'Negative stock — issued at last known cost, flagged for review.' : null,
                    'performed_by' => $issuedByUserId,
                    'occurred_at' => $issuedAt,
                ]);

                if ($pending['is_negative']) {
                    event(new NegativeStockIssued($movement));
                }

                if ($pending['item']->is_capitalisable
                    && $pending['item']->capitalisation_threshold_minor !== null
                    && $pending['unit_cost_minor'] >= $pending['item']->capitalisation_threshold_minor) {
                    event(new ItemCapitalisationDue($pending['item'], $movement, $pending['unit_cost_minor']));
                }
            }

            $requisition->update([
                'status' => 'issued',
                'issued_by' => $issuedByUserId,
                'issued_at' => $issuedAt,
                'total_cost_minor' => $totalCostMinor,
                'journal_id' => $journal->id,
            ]);

            event(new StockIssued($requisition));

            return $requisition;
        });
    }

    private function currentOnHand(int $storeId, int $itemId): float
    {
        $ins = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'in')->sum('quantity');
        $outs = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'out')->sum('quantity');

        return $ins - $outs;
    }
}
