<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Finance\Livewire\Receipts\Capture as ReceiptsCapture;
use Modules\Finance\Livewire\Receipts\Show as ReceiptShow;
use Modules\Finance\Livewire\Receipts\VoidReceipt;
use Modules\Finance\Livewire\Reports\Collections as CollectionsReport;
use Modules\Finance\Livewire\Suspense\Workbench as SuspenseWorkbench;
use Modules\Finance\Livewire\Till\Banking;
use Modules\Finance\Livewire\Till\CashUp;
use Modules\Finance\Livewire\Till\Open as TillOpen;
use Modules\Finance\Livewire\Till\Sessions as TillSessions;
use Modules\Finance\Livewire\Till\VarianceApproval;

/**
 * Book B FIN-04 §3/§4/§5 — Receipting, Cashiering & Till Control admin
 * screens. School-scoped (`{school}`), matching `debtors.php`'s own
 * convention.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/finance')->name('finance.')->group(function (): void {
    Route::livewire('till/open', TillOpen::class)->name('till.open');
    Route::livewire('till/sessions', TillSessions::class)->name('till.sessions');
    Route::livewire('till/banking', Banking::class)->name('till.banking');
    Route::livewire('till/variance-approval', VarianceApproval::class)->name('till.variance-approval');
    Route::livewire('till/{tillSession}/cash-up', CashUp::class)->name('till.cash-up');

    Route::livewire('receipts/capture/{tillSession}', ReceiptsCapture::class)->name('receipts.capture');
    Route::livewire('receipts/{receipt}', ReceiptShow::class)->name('receipts.show');
    Route::livewire('receipts/{receipt}/void', VoidReceipt::class)->name('receipts.void');

    Route::livewire('suspense/workbench', SuspenseWorkbench::class)->name('suspense.workbench');

    Route::livewire('reports/collections', CollectionsReport::class)->name('reports.collections');
});
