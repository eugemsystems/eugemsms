<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Security\Livewire\Contractors\Index as ContractorsIndex;
use Modules\Security\Livewire\Drills\Index as DrillsIndex;
use Modules\Security\Livewire\Keys\Index as KeysIndex;
use Modules\Security\Livewire\LostProperty\Index as LostPropertyIndex;
use Modules\Security\Livewire\Muster\Index as MusterIndex;
use Modules\Security\Livewire\OccurrenceBook\Index as OccurrenceBookIndex;
use Modules\Security\Livewire\Patrols\Index as PatrolsIndex;

/**
 * Book H2 OPS-06 §5 — Security, Gate & Access Control admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/security')->name('security.')->group(function (): void {
    Route::livewire('muster', MusterIndex::class)->name('muster.index');
    Route::livewire('occurrence-book', OccurrenceBookIndex::class)->name('occurrence-book.index');
    Route::livewire('patrols', PatrolsIndex::class)->name('patrols.index');
    Route::livewire('contractors', ContractorsIndex::class)->name('contractors.index');
    Route::livewire('keys', KeysIndex::class)->name('keys.index');
    Route::livewire('lost-property', LostPropertyIndex::class)->name('lost-property.index');
    Route::livewire('drills', DrillsIndex::class)->name('drills.index');
});
