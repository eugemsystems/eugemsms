<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Wallet\Livewire\Pos\Terminal as PosTerminal;
use Modules\Wallet\Livewire\Products\Index as ProductsIndex;
use Modules\Wallet\Livewire\Reports\Reconciliation as ReportsReconciliation;
use Modules\Wallet\Livewire\Reports\Sales as ReportsSales;
use Modules\Wallet\Livewire\SpendPoints\Index as SpendPointsIndex;
use Modules\Wallet\Livewire\TermEnd\Process as TermEndProcess;
use Modules\Wallet\Livewire\Wallets\Index as WalletsIndex;
use Modules\Wallet\Livewire\Wallets\Show as WalletsShow;

/**
 * Book H3 FIN-14 §6 — Student Wallet & Tuckshop admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/wallet')->name('wallet.')->group(function (): void {
    Route::livewire('pos', PosTerminal::class)->name('pos.terminal');
    Route::livewire('products', ProductsIndex::class)->name('products.index');
    Route::livewire('spend-points', SpendPointsIndex::class)->name('spend-points.index');
    Route::livewire('wallets', WalletsIndex::class)->name('wallets.index');
    Route::livewire('wallets/{wallet}', WalletsShow::class)->name('wallets.show');
    Route::livewire('term-end', TermEndProcess::class)->name('term-end.process');
    Route::livewire('reports/reconciliation', ReportsReconciliation::class)->name('reports.reconciliation');
    Route::livewire('reports/sales', ReportsSales::class)->name('reports.sales');
});
