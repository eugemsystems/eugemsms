<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Stores\Domain\DataObjects\RecordGoodsReceivedNoteData;
use Modules\Stores\Domain\Events\CapitalPurchaseReceived;
use Modules\Stores\Domain\Events\GoodsReceived;
use Modules\Stores\Domain\Events\GoodsRejected;
use Modules\Stores\Models\GoodsReceivedNote;
use Modules\Stores\Models\GrnLine;
use Modules\Stores\Models\PurchaseOrder;
use Modules\Stores\Models\StockLot;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\Store;

/**
 * ACT-RecordGoodsReceivedNote (Book H1 FIN-08 §6 ⭐⭐/BR-FIN-08-012/013/
 * 014/AC-FIN-08-006). One journal per GRN (Dr Inventory-or-Expense,
 * grouped by account and cost centre, the same aggregation discipline
 * `FIN-09`'s `IssueStockAction` uses / Cr GRN Accrual), with real
 * `FIN-09` stock lots created for stocked lines in the SAME
 * transaction — built directly here (not by calling
 * `ReceiveStockAction`, which posts its own separate journal per
 * call) so a ten-line delivery still produces one journal, not ten.
 * Rejected goods never enter stock and never appear on the debit side
 * — they simply reduce what's left to invoice.
 */
final class RecordGoodsReceivedNoteAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
        private readonly AllocateNumberAction $allocateNumber,
    ) {}

    public function execute(RecordGoodsReceivedNoteData $data): GoodsReceivedNote
    {
        $order = PurchaseOrder::with('lines')->findOrFail($data->purchaseOrderId);
        $currency = Currency::from($order->currency);

        $poLines = $order->lines->keyBy('id');

        foreach ($data->lines as $line) {
            $poLine = $poLines->get($line['poLineId']);

            if ($poLine === null) {
                throw ValidationException::withMessages([
                    'lines' => "PO line #{$line['poLineId']} does not belong to purchase order #{$order->id}.",
                ]);
            }

            if ($line['quantityAccepted'] + $line['quantityRejected'] > $line['quantityDelivered'] + 0.0001) {
                throw ValidationException::withMessages([
                    'lines' => "Line #{$line['poLineId']}: accepted plus rejected cannot exceed the quantity delivered.",
                ]);
            }

            if ($line['quantityDelivered'] > $poLine->outstandingQuantity() + 0.0001) {
                throw ValidationException::withMessages([
                    'lines' => "Line #{$line['poLineId']}: {$line['quantityDelivered']} delivered exceeds the {$poLine->outstandingQuantity()} still outstanding.",
                ]);
            }
        }

        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'goods_received_note',
            allocatedByUserId: $data->receivedByUserId,
            termId: $data->termId,
        ));

        return $this->transaction(function () use ($data, $order, $poLines, $currency, $number): GoodsReceivedNote {
            /** @var array<string, array{accountId: int, costCentreId: int|null, amount: Money}> $debitLines */
            $debitLines = [];
            $pendingLots = [];
            $hasRejections = false;
            $totalValueMinor = 0;

            foreach ($data->lines as $line) {
                $poLine = $poLines->get($line['poLineId']);
                $acceptedValueMinor = (int) round($line['quantityAccepted'] * $line['unitCostMinor']);
                $totalValueMinor += $acceptedValueMinor;
                $hasRejections = $hasRejections || $line['quantityRejected'] > 0.0;

                $isStocked = $poLine->item_id !== null && $poLine->store_id !== null && ! $poLine->is_capital;

                if ($isStocked && $line['quantityAccepted'] > 0.0) {
                    $store = Store::findOrFail($poLine->store_id);

                    $lot = StockLot::create([
                        'school_id' => $data->schoolId,
                        'store_id' => $store->id,
                        'item_id' => $poLine->item_id,
                        'lot_reference' => $line['batchNumber'] ?? (string) Str::ulid(),
                        'batch_number' => $line['batchNumber'],
                        'received_on' => $data->receivedOn->toDateString(),
                        'expiry_date' => $line['expiryDate']?->toDateString(),
                        'quantity_received' => $line['quantityAccepted'],
                        'quantity_remaining' => $line['quantityAccepted'],
                        'unit_cost_minor' => $line['unitCostMinor'],
                        'currency' => $order->currency,
                        'base_unit_cost_minor' => $line['unitCostMinor'],
                        'source_type' => 'goods_received_note',
                        'is_depleted' => false,
                    ]);

                    $pendingLots[] = ['lot' => $lot, 'store' => $store, 'line' => $line, 'poLine' => $poLine, 'valueMinor' => $acceptedValueMinor];

                    $key = $store->inventory_account_id.'|'.$store->cost_centre_id;
                    $debitLines[$key] ??= ['accountId' => $store->inventory_account_id, 'costCentreId' => $store->cost_centre_id, 'amount' => Money::zero($currency)];
                    $debitLines[$key]['amount'] = $debitLines[$key]['amount']->plus(Money::of($acceptedValueMinor, $currency));
                } elseif ($line['quantityAccepted'] > 0.0) {
                    $key = $poLine->expense_account_id.'|'.$order->cost_centre_id;
                    $debitLines[$key] ??= ['accountId' => $poLine->expense_account_id, 'costCentreId' => $order->cost_centre_id, 'amount' => Money::zero($currency)];
                    $debitLines[$key]['amount'] = $debitLines[$key]['amount']->plus(Money::of($acceptedValueMinor, $currency));
                }
            }

            $journalLines = array_values(array_map(
                fn (array $l): JournalLineData => new JournalLineData(accountId: $l['accountId'], direction: 'DR', amount: $l['amount'], costCentreId: $l['costCentreId']),
                $debitLines,
            ));

            $journalLines[] = new JournalLineData(
                accountId: $data->grnAccrualAccountId,
                direction: 'CR',
                amount: Money::of($totalValueMinor, $currency),
                costCentreId: $order->cost_centre_id,
            );

            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $data->schoolId,
                academicYearId: $order->academic_year_id,
                termId: $data->termId,
                journalType: 'GRN_ACCRUAL',
                narration: "Goods received — {$number->formatted_number} against PO {$order->po_number}",
                lines: $journalLines,
                effectiveAt: $data->receivedOn,
                postedByUserId: $data->receivedByUserId,
                sourceType: 'goods_received_note',
                sourceId: null,
            ));

            $grn = GoodsReceivedNote::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'grn_number' => $number->formatted_number,
                'purchase_order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'delivery_note_ref' => $data->deliveryNoteRef,
                'received_on' => $data->receivedOn->toDateString(),
                'received_by' => $data->receivedByUserId,
                'inspected_by' => $data->inspectedByUserId,
                'is_partial' => false,
                'has_rejections' => $hasRejections,
                'total_value_minor' => $totalValueMinor,
                'currency' => $order->currency,
                'status' => 'posted',
                'journal_id' => $journal->id,
            ]);

            foreach ($pendingLots as $pending) {
                $onHandBefore = $this->currentOnHand($pending['store']->id, $pending['poLine']->item_id);

                StockMovement::create([
                    'school_id' => $data->schoolId,
                    'academic_year_id' => $order->academic_year_id,
                    'term_id' => $data->termId,
                    'store_id' => $pending['store']->id,
                    'item_id' => $pending['poLine']->item_id,
                    'lot_id' => $pending['lot']->id,
                    'movement_type' => 'receipt',
                    'direction' => 'in',
                    'quantity' => $pending['line']['quantityAccepted'],
                    'unit_cost_minor' => $pending['line']['unitCostMinor'],
                    'total_cost_minor' => $pending['valueMinor'],
                    'currency' => $order->currency,
                    'base_total_minor' => $pending['valueMinor'],
                    'balance_after' => $onHandBefore + $pending['line']['quantityAccepted'],
                    'source_type' => 'goods_received_note',
                    'source_id' => $grn->id,
                    'cost_centre_id' => $pending['store']->cost_centre_id,
                    'journal_id' => $journal->id,
                    'performed_by' => $data->receivedByUserId,
                    'occurred_at' => $data->receivedOn,
                ]);
            }

            foreach ($data->lines as $line) {
                $poLine = $poLines->get($line['poLineId']);
                $pending = collect($pendingLots)->firstWhere('poLine.id', $poLine->id);

                $grnLine = GrnLine::create([
                    'school_id' => $data->schoolId,
                    'grn_id' => $grn->id,
                    'po_line_id' => $poLine->id,
                    'item_id' => $poLine->item_id,
                    'quantity_delivered' => $line['quantityDelivered'],
                    'quantity_accepted' => $line['quantityAccepted'],
                    'quantity_rejected' => $line['quantityRejected'],
                    'rejection_reason' => $line['rejectionReason'],
                    'batch_number' => $line['batchNumber'],
                    'expiry_date' => $line['expiryDate']?->toDateString(),
                    'unit_cost_minor' => $line['unitCostMinor'],
                    'stock_lot_id' => $pending !== null ? $pending['lot']->id : null,
                ]);

                if ($line['quantityRejected'] > 0.0) {
                    event(new GoodsRejected($grnLine));
                }

                if ($poLine->is_capital && $line['quantityAccepted'] > 0.0) {
                    event(new CapitalPurchaseReceived($grnLine, $line['unitCostMinor']));
                }

                $poLine->update([
                    'quantity_received' => (float) $poLine->quantity_received + $line['quantityAccepted'],
                    'quantity_rejected' => (float) $poLine->quantity_rejected + $line['quantityRejected'],
                ]);
            }

            $order->update([
                'status' => $order->fresh('lines')->isFullyReceived() ? 'received' : 'partially_received',
            ]);

            event(new GoodsReceived($grn));

            return $grn;
        });
    }

    private function currentOnHand(int $storeId, int $itemId): float
    {
        $ins = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'in')->sum('quantity');
        $outs = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'out')->sum('quantity');

        return $ins - $outs;
    }
}
