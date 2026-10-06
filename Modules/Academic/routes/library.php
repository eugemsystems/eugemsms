<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Academic\Livewire\Library\Acquisitions;
use Modules\Academic\Livewire\Library\BulkIssue;
use Modules\Academic\Livewire\Library\Catalogue;
use Modules\Academic\Livewire\Library\Circulation;
use Modules\Academic\Livewire\Library\Overdue;
use Modules\Academic\Livewire\Library\StockTake;

/**
 * Book K ACA-10 §5 — Library & Textbook Management.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/academic/library')->name('academic.library.')->group(function (): void {
    Route::livewire('catalogue', Catalogue::class)->name('catalogue');
    Route::livewire('circulation', Circulation::class)->name('circulation');
    Route::livewire('bulk-issue', BulkIssue::class)->name('bulk-issue');
    Route::livewire('overdue', Overdue::class)->name('overdue');
    Route::livewire('stock-take', StockTake::class)->name('stock-take');
    Route::livewire('acquisitions', Acquisitions::class)->name('acquisitions');
});
