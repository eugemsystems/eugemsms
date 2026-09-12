<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Documents\Batches;
use Modules\Core\Livewire\Documents\Index;

/**
 * Book A CORE-06 §6 — Document archive & batches. Every screen takes
 * an explicit `{school}` and authorises + sets SchoolContext itself
 * (`InteractsWithSchool`), matching schools.php's/roles.php's own
 * convention.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('schools/{school}/documents', Index::class)->name('documents.index');
    Route::livewire('schools/{school}/documents/batches', Batches::class)->name('documents.batches');
});
