<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Academic\Livewire\Cbt\Bank;
use Modules\Academic\Livewire\Cbt\Builder;
use Modules\Academic\Livewire\Cbt\ItemAnalysis;
use Modules\Academic\Livewire\Cbt\ManualMarking;
use Modules\Academic\Livewire\Cbt\Monitor;

/**
 * Book K ACA-09 §5 — Computer-Based Testing, the staff side. The candidate's
 * test-taking screen is the learner app's job (`/api/v1/cbt/*`); nothing
 * here lets a member of staff answer on a candidate's behalf.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/academic/cbt')->name('academic.cbt.')->group(function (): void {
    Route::livewire('bank', Bank::class)->name('bank');
    Route::livewire('tests', Builder::class)->name('builder');
    Route::livewire('monitor', Monitor::class)->name('monitor');
    Route::livewire('marking', ManualMarking::class)->name('marking');
    Route::livewire('item-analysis', ItemAnalysis::class)->name('item-analysis');
});
