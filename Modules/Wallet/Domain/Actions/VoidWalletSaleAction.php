<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Domain\Actions\ReverseJournalAction;
use Modules\Finance\Domain\DataObjects\ReverseJournalData;
use Modules\Fiscal\Domain\Actions\RaiseFiscalCreditNoteAction;
use Modules\Fiscal\Domain\DataObjects\RaiseFiscalCreditNoteData;
use Modules\Stores\Models\StockMovement;
use Modules\Wallet\Domain\DataObjects\VoidWalletSaleData;
use Modules\Wallet\Models\StudentWallet;
use Modules\Wallet\Models\WalletSale;
use Modules\Wallet\Models\WalletTransaction;

/**
 * ACT-VoidWalletSale (Book H3 FIN-14 §5/BR-FIN-14-018). Reverses the
 * wallet movement (a new, compensating `WalletTransaction` —
 * `wallet_transactions` is append-only, never edited), the stock
 * movement (a compensating `in` movement per depleted line, the same
 * "correction is a new record" doctrine), and the journal (the real
 * `Modules\Finance\Domain\Actions\ReverseJournalAction`, FIN-01's own
 * mirror-the-lines reversal). Raises a fiscal credit note through the
 * real `Modules\Fiscal\Domain\Actions\RaiseFiscalCreditNoteAction`
 * only when the sale was actually fiscalised.
 */
final class VoidWalletSaleAction extends Action
{
    public function __construct(
        private readonly ReverseJournalAction $reverseJournal,
        private readonly RaiseFiscalCreditNoteAction $raiseFiscalCreditNote,
    ) {}

    public function execute(VoidWalletSaleData $data): WalletSale
    {
        $sale = WalletSale::with('lines')->findOrFail($data->saleId);

        if ($sale->status === 'voided') {
            throw new InvalidStateTransitionException("Wallet sale #{$sale->id} is already voided.", ['sale_id' => $sale->id]);
        }

        return $this->transaction(function () use ($sale, $data): WalletSale {
            if ($sale->journal_id !== null) {
                $this->reverseJournal->execute(new ReverseJournalData(
                    journalId: $sale->journal_id, reason: $data->reason, reversedByUserId: $data->voidedByUserId,
                ));
            }

            foreach ($sale->lines as $line) {
                if ($line->stock_movement_id === null) {
                    continue;
                }

                $original = StockMovement::findOrFail($line->stock_movement_id);

                StockMovement::create([
                    'school_id' => $original->school_id,
                    'academic_year_id' => $original->academic_year_id,
                    'term_id' => $original->term_id,
                    'store_id' => $original->store_id,
                    'item_id' => $original->item_id,
                    'lot_id' => $original->lot_id,
                    'movement_type' => 'sale_void',
                    'direction' => 'in',
                    'quantity' => $original->quantity,
                    'unit_cost_minor' => $original->unit_cost_minor,
                    'total_cost_minor' => $original->total_cost_minor,
                    'currency' => $original->currency,
                    'base_total_minor' => $original->base_total_minor,
                    'balance_after' => $original->balance_after + $original->quantity,
                    'source_type' => 'wallet_sale_void',
                    'source_id' => $sale->id,
                    'cost_centre_id' => $original->cost_centre_id,
                    'expense_account_id' => $original->expense_account_id,
                    'performed_by' => $data->voidedByUserId,
                    'occurred_at' => Carbon::now(),
                ]);
            }

            if ($sale->wallet_id !== null) {
                $wallet = StudentWallet::findOrFail($sale->wallet_id);
                $newBalance = $wallet->balance_minor + $sale->subtotal_minor;

                WalletTransaction::create([
                    'school_id' => $sale->school_id,
                    'term_id' => $sale->term_id,
                    'wallet_id' => $wallet->id,
                    'transaction_type' => 'refund',
                    'direction' => 'in',
                    'amount_minor' => $sale->subtotal_minor,
                    'balance_after_minor' => $newBalance,
                    'currency' => $sale->currency,
                    'spend_point_id' => $sale->spend_point_id,
                    'sale_id' => $sale->id,
                    'reference' => "Void: {$data->reason}",
                    'performed_by' => $data->voidedByUserId,
                    'occurred_at' => Carbon::now(),
                ]);

                $wallet->update(['balance_minor' => $newBalance, 'last_transaction_at' => Carbon::now()]);
            }

            if ($sale->fiscal_receipt_id !== null) {
                $this->raiseFiscalCreditNote->execute(new RaiseFiscalCreditNoteData(
                    originalFiscalReceiptId: $sale->fiscal_receipt_id, creditReason: $data->reason,
                ));
            }

            return tap($sale)->update(['status' => 'voided']);
        });
    }
}
