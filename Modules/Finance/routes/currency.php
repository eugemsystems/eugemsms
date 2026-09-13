<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Finance\Livewire\Currency\ApproveRate;
use Modules\Finance\Livewire\Currency\CaptureRate;
use Modules\Finance\Livewire\Currency\ConversionLog;
use Modules\Finance\Livewire\Currency\Index as CurrencyIndex;
use Modules\Finance\Livewire\Currency\Rates;
use Modules\Finance\Livewire\Currency\Revaluation;
use Modules\Finance\Livewire\Currency\Simulate;

/**
 * Book B FIN-06 §6 — Multi-Currency & FX Engine admin screens. Every
 * route is school-scoped (`{school}`), matching `ledger.php`'s own
 * convention.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/finance/currency')->name('finance.currency.')->group(function (): void {
    Route::livewire('/', CurrencyIndex::class)->name('index');
    Route::livewire('rates', Rates::class)->name('rates');
    Route::livewire('rates/capture', CaptureRate::class)->name('capture-rate');
    Route::livewire('rates/approve', ApproveRate::class)->name('approve-rate');
    Route::livewire('simulate', Simulate::class)->name('simulate');
    Route::livewire('revaluation', Revaluation::class)->name('revaluation');
    Route::livewire('conversions', ConversionLog::class)->name('conversion-log');
});
