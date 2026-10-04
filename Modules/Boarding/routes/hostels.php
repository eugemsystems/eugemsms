<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Boarding\Livewire\Allocation\Board;
use Modules\Boarding\Livewire\Allocation\Constraints;
use Modules\Boarding\Livewire\Allocation\Incompatibilities;
use Modules\Boarding\Livewire\Allocation\Run;
use Modules\Boarding\Livewire\Allocation\Waitlist;
use Modules\Boarding\Livewire\Damages\Index as DamagesIndex;
use Modules\Boarding\Livewire\Hostels\Show as HostelShow;
use Modules\Boarding\Livewire\Hostels\Structure;
use Modules\Boarding\Livewire\Inspections\Index as InspectionsIndex;

/**
 * Book F BRD-01 §5 — Hostel, Room & Bed Allocation admin screens.
 * School-scoped, matching `people/staff.php`'s own route convention.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/boarding')->name('boarding.')->group(function (): void {
    Route::livewire('hostels', Structure::class)->name('hostels.structure');
    Route::livewire('hostels/{hostel}', HostelShow::class)->name('hostels.show');

    Route::livewire('allocation/board', Board::class)->name('allocation.board');
    Route::livewire('allocation/run', Run::class)->name('allocation.run');
    Route::livewire('allocation/waitlist', Waitlist::class)->name('allocation.waitlist');
    Route::livewire('allocation/constraints', Constraints::class)->name('allocation.constraints');
    Route::livewire('allocation/incompatibilities', Incompatibilities::class)->name('allocation.incompatibilities');

    Route::livewire('inspections', InspectionsIndex::class)->name('inspections.index');
    Route::livewire('damages', DamagesIndex::class)->name('damages.index');
});
