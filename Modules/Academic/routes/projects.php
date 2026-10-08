<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Academic\Livewire\Projects\Amend;
use Modules\Academic\Livewire\Projects\Approve;
use Modules\Academic\Livewire\Projects\Briefs;
use Modules\Academic\Livewire\Projects\CalaArchive;
use Modules\Academic\Livewire\Projects\Export;
use Modules\Academic\Livewire\Projects\Instruments;
use Modules\Academic\Livewire\Projects\Mark;
use Modules\Academic\Livewire\Projects\Moderate;
use Modules\Academic\Livewire\Projects\Portfolio;
use Modules\Academic\Livewire\Projects\Rubrics;
use Modules\Academic\Livewire\Projects\Tracker;
use Modules\Academic\Livewire\Projects\Verify;

/**
 * Book E ACA-06 §7 — School-Based Projects & Legacy CALA admin screens.
 * The national submission export (`Export`) renders through a school's
 * own configurable `DocumentTemplate`, not a hard-coded Ministry
 * layout — none is specified anywhere in the spec to build against.
 * See `.ai/rules/academic.md`. School-scoped.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/academic')->name('academic.')->group(function (): void {
    Route::livewire('projects/instruments', Instruments::class)->name('projects.instruments');
    Route::livewire('projects/briefs', Briefs::class)->name('projects.briefs');
    Route::livewire('projects/rubrics', Rubrics::class)->name('projects.rubrics');
    Route::livewire('projects/approve', Approve::class)->name('projects.approve');
    Route::livewire('projects/tracker', Tracker::class)->name('projects.tracker');
    Route::livewire('projects/mark', Mark::class)->name('projects.mark');
    Route::livewire('projects/moderate', Moderate::class)->name('projects.moderate');
    Route::livewire('projects/verify', Verify::class)->name('projects.verify');
    Route::livewire('projects/cala-archive', CalaArchive::class)->name('projects.cala-archive');
    Route::livewire('projects/{learnerProject}/amend', Amend::class)->name('projects.amend');
    Route::livewire('projects/{learnerProject}/portfolio', Portfolio::class)->name('projects.portfolio');
    Route::livewire('projects/export', Export::class)->name('projects.export');
});
