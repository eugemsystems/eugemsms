<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Facilities\Livewire\Calendar\Index as CalendarIndex;
use Modules\Facilities\Livewire\Hire\Index as HireIndex;
use Modules\Facilities\Livewire\Request\Index as RequestIndex;
use Modules\Facilities\Livewire\Resources\Index as ResourcesIndex;
use Modules\Facilities\Livewire\Utilisation\Index as UtilisationIndex;

/**
 * Book H2 OPS-05 §4 — Facilities Booking & External Hire admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/facilities')->name('facilities.')->group(function (): void {
    Route::livewire('resources', ResourcesIndex::class)->name('resources.index');
    Route::livewire('calendar', CalendarIndex::class)->name('calendar.index');
    Route::livewire('request', RequestIndex::class)->name('request.index');
    Route::livewire('hire', HireIndex::class)->name('hire.index');
    Route::livewire('utilisation', UtilisationIndex::class)->name('utilisation.index');
});
