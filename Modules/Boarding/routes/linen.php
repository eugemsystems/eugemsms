<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Boarding\Livewire\Laundry\Cycles;
use Modules\Boarding\Livewire\Laundry\Missing;
use Modules\Boarding\Livewire\Linen\Clearance;
use Modules\Boarding\Livewire\Linen\Issue;
use Modules\Boarding\Livewire\Linen\Items;

/**
 * Book F BRD-05 §4 — Laundry & Linen admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/boarding')->name('boarding.')->group(function (): void {
    Route::livewire('linen/items', Items::class)->name('linen.items');
    Route::livewire('linen/issue', Issue::class)->name('linen.issue');
    Route::livewire('linen/clearance', Clearance::class)->name('linen.clearance');

    Route::livewire('laundry/cycles', Cycles::class)->name('laundry.cycles');
    Route::livewire('laundry/missing', Missing::class)->name('laundry.missing');
});
