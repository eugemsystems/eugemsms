<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Fiscal\Livewire\Audit\Index as AuditIndex;
use Modules\Fiscal\Livewire\Days\Index as DaysIndex;
use Modules\Fiscal\Livewire\Devices\Index as DevicesIndex;
use Modules\Fiscal\Livewire\Queue\Status as QueueStatus;
use Modules\Fiscal\Livewire\Receipts\Index as ReceiptsIndex;
use Modules\Fiscal\Livewire\Receipts\Retry as ReceiptsRetry;
use Modules\Fiscal\Livewire\Reconciliation\Index as ReconciliationIndex;
use Modules\Fiscal\Livewire\Reports\ZReports;
use Modules\Fiscal\Livewire\Rules\Index as RulesIndex;

/**
 * Book H3 FIN-13 §7 — ZIMRA Fiscalisation (FDMS) admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/fiscal')->name('fiscal.')->group(function (): void {
    Route::livewire('devices', DevicesIndex::class)->name('devices.index');
    Route::livewire('rules', RulesIndex::class)->name('rules.index');
    Route::livewire('days', DaysIndex::class)->name('days.index');
    Route::livewire('receipts', ReceiptsIndex::class)->name('receipts.index');
    Route::livewire('receipts/retry', ReceiptsRetry::class)->name('receipts.retry');
    Route::livewire('queue', QueueStatus::class)->name('queue.status');
    Route::livewire('z-reports', ZReports::class)->name('reports.z-reports');
    Route::livewire('reconciliation', ReconciliationIndex::class)->name('reconciliation.index');
    Route::livewire('audit', AuditIndex::class)->name('audit.index');
});
