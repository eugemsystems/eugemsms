<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\Exceptions\NoOpenTillSessionException;
use Modules\Finance\Models\TillSession;
use Modules\Fiscal\Domain\Actions\RouteReceiptForFiscalisationAction;
use Modules\Fiscal\Domain\DataObjects\RouteReceiptForFiscalisationData;
use Modules\Stores\Domain\Exceptions\InsufficientStockException;
use Modules\Stores\Domain\Support\StockCostingEngine;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\Store;
use Modules\Wallet\Domain\DataObjects\ProcessWalletSaleData;
use Modules\Wallet\Domain\Events\BlockedCategoryAttempt;
use Modules\Wallet\Domain\Events\SpendingLimitReached;
use Modules\Wallet\Domain\Events\WalletLowBalance;
use Modules\Wallet\Domain\Events\WalletNegative;
use Modules\Wallet\Domain\Events\WalletPurchase;
use Modules\Wallet\Domain\Exceptions\BlockedCategoryException;
use Modules\Wallet\Domain\Exceptions\InsufficientWalletBalanceException;
use Modules\Wallet\Domain\Exceptions\SpendingLimitExceededException;
use Modules\Wallet\Models\SpendPoint;
use Modules\Wallet\Models\StudentWallet;
use Modules\Wallet\Models\WalletProduct;
use Modules\Wallet\Models\WalletSale;
use Modules\Wallet\Models\WalletSaleLine;
use Modules\Wallet\Models\WalletTransaction;

/**
 * ACT-ProcessWalletSale (Book H3 FIN-14 §3/§4 ⭐/BR-FIN-14-001/003/
 * 005/006/007/009/010/017). The online (connected) POS path — a sale
 * against a wallet refuses outright rather than go negative here;
 * `SyncOfflineWalletSaleAction` is the separate path that honours an
 * already-happened offline sale against a since-changed balance
 * (BR-FIN-14-012). Category blocks and limits are enforced
 * server-side against the wallet's own stored controls, never
 * trusted from the caller. One journal per sale covers both the
 * revenue side (`Dr` wallet liability or cash, `Cr` the spend
 * point's income account) and, for lines whose product has a real
 * `FIN-09` `item_id`, the cost-of-sales side (`Dr` cost of sales,
 * `Cr` inventory) — the exact pairing the spec's own worked example
 * shows, in one call rather than `Modules\Stores`'s
 * `IssueSaleableItemToLearnerAction` (which assumes the wrong
 * revenue mechanism — a debtor charge, not a wallet debit — so isn't
 * reused here; `StockCostingEngine` itself, the FIFO engine that
 * action also uses, is reused directly).
 */
final class ProcessWalletSaleAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly StockCostingEngine $costingEngine,
        private readonly PostJournalAction $postJournal,
        private readonly RouteReceiptForFiscalisationAction $routeForFiscalisation,
    ) {}

    /**
     * @param  bool  $allowNegative  set only by `SyncOfflineWalletSaleAction` — an offline sale already
     *                               happened and is honoured even against a since-changed insufficient
     *                               balance (BR-FIN-14-012); the online path here never sets this.
     */
    public function execute(ProcessWalletSaleData $data, bool $allowNegative = false): WalletSale
    {
        if ($data->offlineReference !== null) {
            $existing = WalletSale::where('school_id', $data->schoolId)->where('offline_reference', $data->offlineReference)->first();

            if ($existing !== null) {
                return $existing->fresh(['lines']);
            }
        }

        $spendPoint = SpendPoint::findOrFail($data->spendPointId);
        $soldAt = $data->soldAt ?? Carbon::now();

        $wallet = null;

        if ($data->paymentMethod === 'wallet') {
            $wallet = StudentWallet::where('school_id', $data->schoolId)->where('student_id', $data->studentId)->firstOrFail();
        }

        $products = WalletProduct::whereIn('id', array_column($data->lines, 'product_id'))->get()->keyBy('id');

        $this->assertNoBlockedCategory($wallet, $products, $data->lines);

        $currency = Currency::from($products->first()->currency ?? 'USD');
        $subtotalMinor = 0;

        foreach ($data->lines as $line) {
            $subtotalMinor += (int) round($products[$line['product_id']]->price_minor * $line['quantity']);
        }

        if ($wallet !== null && ! $allowNegative) {
            $this->assertWithinLimits($wallet, $subtotalMinor);

            if ($subtotalMinor > $wallet->balance_minor) {
                throw InsufficientWalletBalanceException::forWallet($wallet->id, $subtotalMinor - $wallet->balance_minor);
            }
        }

        if ($data->paymentMethod === 'cash') {
            $session = TillSession::find($data->tillSessionId);

            if ($session === null || $session->status !== 'open' || $session->cashier_id !== $data->operatorId) {
                throw NoOpenTillSessionException::forSession($data->tillSessionId);
            }
        }

        return $this->transaction(function () use ($data, $spendPoint, $wallet, $products, $currency, $subtotalMinor, $soldAt): WalletSale {
            $number = $this->allocateNumber->execute(new AllocateNumberData(
                schoolId: $data->schoolId, documentType: 'wallet_sale', allocatedByUserId: $data->operatorId,
                academicYearId: $data->academicYearId, termId: $data->termId,
            ));

            $lines = [];
            $costOfSalesMinor = 0;

            foreach ($data->lines as $line) {
                $product = $products[$line['product_id']];
                $lineTotal = (int) round($product->price_minor * $line['quantity']);
                $stockMovementId = null;

                if ($product->item_id !== null) {
                    $movementResult = $this->depleteStock($data, $product, $line['quantity'], $soldAt);
                    $stockMovementId = $movementResult['movement']->id;
                    $costOfSalesMinor += $movementResult['costMinor'];
                }

                $lines[] = ['product' => $product, 'quantity' => $line['quantity'], 'lineTotal' => $lineTotal, 'stockMovementId' => $stockMovementId];
            }

            $paymentAccountId = $data->paymentMethod === 'wallet'
                ? $wallet->liability_account_id
                : $spendPoint->till?->cash_account_id;

            $journalLines = [
                new JournalLineData(
                    accountId: $paymentAccountId, direction: 'DR', amount: Money::of($subtotalMinor, $currency),
                    subledgerType: $data->paymentMethod === 'wallet' ? 'student' : null,
                    subledgerId: $data->paymentMethod === 'wallet' ? $wallet->student_id : null,
                ),
                new JournalLineData(accountId: $spendPoint->income_account_id, direction: 'CR', amount: Money::of($subtotalMinor, $currency), costCentreId: $spendPoint->cost_centre_id),
            ];

            if ($costOfSalesMinor > 0) {
                $store = Store::find($spendPoint->store_id);
                $journalLines[] = new JournalLineData(accountId: $store->default_expense_account_id, direction: 'DR', amount: Money::of($costOfSalesMinor, $currency), costCentreId: $store->cost_centre_id, narration: 'Cost of sales');
                $journalLines[] = new JournalLineData(accountId: $store->inventory_account_id, direction: 'CR', amount: Money::of($costOfSalesMinor, $currency));
            }

            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $data->schoolId, academicYearId: $data->academicYearId, termId: $data->termId,
                journalType: 'WALLET_SALE', narration: "Wallet sale {$number->formatted_number}",
                lines: $journalLines, effectiveAt: $soldAt, postedByUserId: $data->operatorId,
                sourceType: 'wallet_sale', sourceId: null,
            ));

            $sale = WalletSale::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'spend_point_id' => $spendPoint->id,
                'sale_number' => $number->formatted_number,
                'student_id' => $data->studentId,
                'wallet_id' => $wallet?->id,
                'sold_at' => $soldAt,
                'subtotal_minor' => $subtotalMinor,
                'total_minor' => $subtotalMinor,
                'currency' => $currency->value,
                'payment_method' => $data->paymentMethod,
                'identification_method' => $data->identificationMethod,
                'cost_of_sales_minor' => $costOfSalesMinor > 0 ? $costOfSalesMinor : null,
                'operator_id' => $data->operatorId,
                'till_session_id' => $data->tillSessionId,
                'journal_id' => $journal->id,
                'device_source' => $data->deviceSource,
                'offline_reference' => $data->offlineReference,
                'status' => 'completed',
            ]);

            foreach ($lines as $line) {
                WalletSaleLine::create([
                    'school_id' => $data->schoolId,
                    'sale_id' => $sale->id,
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                    'unit_price_minor' => $line['product']->price_minor,
                    'line_total_minor' => $line['lineTotal'],
                    'tax_type' => $line['product']->tax_type,
                    'stock_movement_id' => $line['stockMovementId'],
                ]);
            }

            if ($wallet !== null) {
                $newBalance = $wallet->balance_minor - $subtotalMinor;

                WalletTransaction::create([
                    'school_id' => $data->schoolId,
                    'term_id' => $data->termId,
                    'wallet_id' => $wallet->id,
                    'transaction_type' => 'purchase',
                    'direction' => 'out',
                    'amount_minor' => $subtotalMinor,
                    'balance_after_minor' => $newBalance,
                    'currency' => $currency->value,
                    'spend_point_id' => $spendPoint->id,
                    'sale_id' => $sale->id,
                    'journal_id' => $journal->id,
                    'performed_by' => $data->operatorId,
                    'occurred_at' => $soldAt,
                ]);

                $wallet->update(['balance_minor' => $newBalance, 'last_transaction_at' => $soldAt]);

                if ($newBalance < 0) {
                    event(new WalletNegative($wallet->fresh()));
                } elseif ($wallet->low_balance_threshold_minor !== null && $newBalance <= $wallet->low_balance_threshold_minor) {
                    event(new WalletLowBalance($wallet->fresh()));
                }
            }

            if ($spendPoint->is_fiscalisable) {
                $fiscalReceipt = $this->routeForFiscalisation->execute(new RouteReceiptForFiscalisationData(
                    schoolId: $data->schoolId, sourceType: 'wallet_product', sourceId: $sale->id,
                    receiptType: 'fiscal_invoice', currency: $currency->value, invoiceNumber: $number->formatted_number,
                    receiptDate: $soldAt,
                    lines: array_map(fn (array $line): array => [
                        'source_identifier' => $line['product']->category,
                        'description' => $line['product']->name,
                        'amount_minor' => $line['lineTotal'],
                    ], $lines),
                    paymentMethods: [$data->paymentMethod], performedByUserId: $data->operatorId,
                ));

                if ($fiscalReceipt !== null) {
                    $sale->update(['fiscal_receipt_id' => $fiscalReceipt->id]);
                }
            }

            event(new WalletPurchase($sale->fresh()));

            return $sale->fresh(['lines']);
        });
    }

    /**
     * @param  Collection<int, WalletProduct>  $products
     * @param  array<int, array{product_id: int, quantity: float}>  $lines
     */
    private function assertNoBlockedCategory(?StudentWallet $wallet, $products, array $lines): void
    {
        if ($wallet === null || $wallet->blocked_categories === null) {
            return;
        }

        foreach ($lines as $line) {
            $product = $products[$line['product_id']];

            if (in_array($product->category, $wallet->blocked_categories, true)) {
                event(new BlockedCategoryAttempt($wallet->id, $product->category));

                throw BlockedCategoryException::forCategory($product->category, $product->name);
            }
        }
    }

    private function assertWithinLimits(StudentWallet $wallet, int $subtotalMinor): void
    {
        if ($wallet->per_transaction_limit_minor !== null && $subtotalMinor > $wallet->per_transaction_limit_minor) {
            event(new SpendingLimitReached($wallet->id, 'per_transaction'));

            throw SpendingLimitExceededException::forLimit('per-transaction', $wallet->per_transaction_limit_minor);
        }

        if ($wallet->daily_limit_minor !== null) {
            $spentToday = (int) WalletTransaction::where('wallet_id', $wallet->id)
                ->where('transaction_type', 'purchase')
                ->whereDate('occurred_at', Carbon::now()->toDateString())
                ->sum('amount_minor');

            $remaining = $wallet->daily_limit_minor - $spentToday;

            if ($subtotalMinor > $remaining) {
                event(new SpendingLimitReached($wallet->id, 'daily'));

                throw SpendingLimitExceededException::forLimit('daily', max(0, $remaining));
            }
        }

        if ($wallet->weekly_limit_minor !== null) {
            $spentThisWeek = (int) WalletTransaction::where('wallet_id', $wallet->id)
                ->where('transaction_type', 'purchase')
                ->where('occurred_at', '>=', Carbon::now()->startOfWeek())
                ->sum('amount_minor');

            $remaining = $wallet->weekly_limit_minor - $spentThisWeek;

            if ($subtotalMinor > $remaining) {
                event(new SpendingLimitReached($wallet->id, 'weekly'));

                throw SpendingLimitExceededException::forLimit('weekly', max(0, $remaining));
            }
        }
    }

    /**
     * @return array{movement: StockMovement, costMinor: int}
     */
    private function depleteStock(ProcessWalletSaleData $data, WalletProduct $product, float $quantity, CarbonInterface $soldAt): array
    {
        $store = Store::findOrFail($product->spendPoint->store_id);
        $item = InventoryItem::findOrFail($product->item_id);

        $result = $this->costingEngine->consume($store, $item, $quantity);

        if (! $result->isFullyConsumed() && ! $store->allows_negative_stock) {
            throw InsufficientStockException::forItem($item->id, $result->shortfallQuantity);
        }

        $costMinor = $result->totalCostMinor();
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
            'quantity' => $quantity,
            'unit_cost_minor' => $quantity > 0 ? (int) round($costMinor / $quantity) : 0,
            'total_cost_minor' => $costMinor,
            'currency' => (string) $item->sale_currency,
            'base_total_minor' => $costMinor,
            'balance_after' => $onHandBefore - $quantity,
            'source_type' => 'wallet_sale',
            'cost_centre_id' => $store->cost_centre_id,
            'expense_account_id' => $item->expense_account_id ?? $store->default_expense_account_id,
            'performed_by' => $data->operatorId,
            'occurred_at' => $soldAt,
        ]);

        return ['movement' => $movement, 'costMinor' => $costMinor];
    }

    private function currentOnHand(int $storeId, int $itemId): float
    {
        $ins = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'in')->sum('quantity');
        $outs = (float) StockMovement::where('store_id', $storeId)->where('item_id', $itemId)->where('direction', 'out')->sum('quantity');

        return $ins - $outs;
    }
}
