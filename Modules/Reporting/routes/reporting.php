<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reporting\Livewire\Close\Checklist as CloseChecklist;
use Modules\Reporting\Livewire\Export\Accounting as ExportAccounting;
use Modules\Reporting\Livewire\Financial\IncomeStatement;
use Modules\Reporting\Livewire\Financial\TrialBalance;
use Modules\Reporting\Livewire\Schedules\Index as SchedulesIndex;

/**
 * Book H3 FIN-12 §6 — Financial Reporting & Period Close admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/reporting')->name('reporting.')->group(function (): void {
    Route::livewire('trial-balance', TrialBalance::class)->name('trial-balance');
    Route::livewire('income-statement', IncomeStatement::class)->name('income-statement');
    Route::livewire('close-checklist', CloseChecklist::class)->name('close-checklist');
    Route::livewire('schedules', SchedulesIndex::class)->name('schedules.index');
    Route::livewire('export/accounting', ExportAccounting::class)->name('export.accounting');
});
