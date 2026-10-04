<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Stores\Livewire\Anomalies\Index as AnomaliesIndex;
use Modules\Stores\Livewire\Items\Index as ItemsIndex;
use Modules\Stores\Livewire\Receipts\Create as ReceiptsCreate;
use Modules\Stores\Livewire\Reports\Consumption as ConsumptionReport;
use Modules\Stores\Livewire\Reports\Valuation as ValuationReport;
use Modules\Stores\Livewire\Requisitions\Create as RequisitionsCreate;
use Modules\Stores\Livewire\Requisitions\Issue as RequisitionsIssue;
use Modules\Stores\Livewire\Requisitions\ReturnItems as RequisitionsReturn;
use Modules\Stores\Livewire\Stock\Expiry as StockExpiry;
use Modules\Stores\Livewire\Stock\ItemLedger;
use Modules\Stores\Livewire\Stock\OnHand;
use Modules\Stores\Livewire\Stock\SellToLearner;
use Modules\Stores\Livewire\StockTake\Count as StockTakeCount;
use Modules\Stores\Livewire\StockTake\Variance as StockTakeVariance;
use Modules\Stores\Livewire\Stores\Index as StoresIndex;
use Modules\Stores\Livewire\Transfers\Index as TransfersIndex;

/**
 * Book H1 FIN-09 §7 — Inventory, Stores & Requisitions admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/stores/inventory')->name('stores.inventory.')->group(function (): void {
    Route::livewire('stores', StoresIndex::class)->name('stores.index');
    Route::livewire('items', ItemsIndex::class)->name('items.index');
    Route::livewire('stock/on-hand', OnHand::class)->name('stock.on-hand');
    Route::livewire('stock/ledger', ItemLedger::class)->name('stock.item-ledger');
    Route::livewire('stock/expiry', StockExpiry::class)->name('stock.expiry');
    Route::livewire('stock/sell', SellToLearner::class)->name('stock.sell-to-learner');
    Route::livewire('receipts/create', ReceiptsCreate::class)->name('receipts.create');
    Route::livewire('requisitions/create', RequisitionsCreate::class)->name('requisitions.create');
    Route::livewire('requisitions/issue', RequisitionsIssue::class)->name('requisitions.issue');
    Route::livewire('requisitions/return', RequisitionsReturn::class)->name('requisitions.return');
    Route::livewire('transfers', TransfersIndex::class)->name('transfers.index');
    Route::livewire('stocktake/count', StockTakeCount::class)->name('stocktake.count');
    Route::livewire('stocktake/variance', StockTakeVariance::class)->name('stocktake.variance');
    Route::livewire('anomalies', AnomaliesIndex::class)->name('anomalies.index');
    Route::livewire('reports/valuation', ValuationReport::class)->name('reports.valuation');
    Route::livewire('reports/consumption', ConsumptionReport::class)->name('reports.consumption');
});
