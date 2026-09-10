<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\CreateAdHocChargeAction;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Stores\Domain\DataObjects\IssueSaleableItemToLearnerData;
use Modules\Stores\Domain\Events\SaleableItemIssued;
use Modules\Stores\Domain\Exceptions\InsufficientStockException;
use Modules\Stores\Domain\Support\StockCostingEngine;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\Store;

/**
 * ACT-IssueSaleableItemToLearner (Book H1 FIN-09 §6/BR-FIN-09-020/
 * AC-FIN-09-010). A uniform or textbook issue is never a store
 * requisition — it is a direct sale to a learner. The revenue side
 * (`Dr Debtors / Cr Sales Revenue`) is `FIN-02`'s own
 * `CreateAdHocChargeAction`, unchanged; this action posts ONLY the
 * cost-of-sales side (`Dr Cost of Sales / Cr Inventory`), separately,
 * per BR-FIN-09-020's own wording.
 */
final class IssueSaleableItemToLearnerAction extends Action
{
    public function __construct(
        private readonly StockCostingEngine $engine,
        private readonly PostJournalAction $postJournal,
        private readonly CreateAdHocChargeAction $createAdHocCharge,
    ) {}

    public function execute(IssueSaleableItemToLearnerData $data): StockMovement
    {
        $store = Store::findOrFail($data->storeId);
        $item = InventoryItem::findOrFail($data->itemId);

        if (! $item->is_saleable || $item->sale_price_minor === null || $item->sale_fee_component_id === null) {
            throw ValidationException::withMessages([
                'itemId' => "{$item->name} is not configured as a saleable item with a sale price and fee component.",
            ]);
        }

        $result = $this->engine->consume($store, $item, $data->quantity);

        if (! $result->isFullyConsumed() && ! $store->allows_negative_stock) {
            throw InsufficientStockException::forItem($item->id, $result->shortfallQuantity);
        }

        $currency = Currency::from((string) $item->sale_currency);
        $costOfSalesMinor = $result->totalCostMinor();

        return $this->transaction(function () use ($data, $store, $item, $result, $currency, $costOfSalesMinor): StockMovement {
            $charge = $this->createAdHocCharge->execute(new CreateAdHocChargeData(
                schoolId: $data->schoolId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                studentId: $data->studentId,
                componentId: $item->sale_fee_component_id,
                description: "Sale: {$item->name}",
                unitRateMinor: (int) $item->sale_price_minor,
                currency: (string) $item->sale_currency,
                raisedByUserId: $data->issuedByUserId,
                quantity: (string) $data->quantity,
                sourceType: 'inventory_item_sale',
                sourceId: $item->id,
            ));

            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $data->schoolId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                journalType: 'STOCK_SALE_COGS',
                narration: "Cost of sales — {$item->name} to learner",
                lines: [
                    new JournalLineData(
                        accountId: $item->expense_account_id ?? $store->default_expense_account_id,
                        direction: 'DR',
                        amount: Money::of($costOfSalesMinor, $currency),
                        costCentreId: $store->cost_centre_id,
                    ),
                    new JournalLineData(
                        accountId: $store->inventory_account_id,
                        direction: 'CR',
                        amount: Money::of($costOfSalesMinor, $currency),
                    ),
                ],
                effectiveAt: $data->issuedAt,
                postedByUserId: $data->issuedByUserId,
                sourceType: 'ad_hoc_charge',
                sourceId: $charge->id,
            ));

            $lot = $result->consumptions->first();
            $onHandBefore = $this->currentOnHand($store->id, $item->id);

            $movement = StockMovement::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'store_id' => $store->id,
                'item_id' => $item->id,
                'lot_id' => $lot?->lot->id,
                'movement_type' => 'sale',
                'direction' => 'out',
                'quantity' => $data->quantity,
                'unit_cost_minor' => $data->quantity > 0 ? (int) round($costOfSalesMinor / $data->quantity) : 0,
                'total_cost_minor' => $costOfSalesMinor,
                'currency' => (string) $item->sale_currency,
                'base_total_minor' => $costOfSalesMinor,
                'balance_after' => $onHandBefore - $data->quantity,
                'source_type' => 'ad_hoc_charge',
                'source_id' => $charge->id,
                'cost_centre_id' => $store->cost_centre_id,
                'expense_account_id' => $item->expense_account_id ?? $store->default_expense_account_id,
                'journal_id' => $journal->id,
                'performed_by' => $data->issuedByUserId,
                'occurred_at' => $data->issuedAt,
            ]);

            event(new SaleableItemIssued($movement, $charge->id));

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
