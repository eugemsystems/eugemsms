<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Academic\Livewire\Timetable\Clashes;
use Modules\Academic\Livewire\Timetable\Constraints;
use Modules\Academic\Livewire\Timetable\Cover;
use Modules\Academic\Livewire\Timetable\Editor;
use Modules\Academic\Livewire\Timetable\ExamPlanner;
use Modules\Academic\Livewire\Timetable\Exceptions;
use Modules\Academic\Livewire\Timetable\Generate;
use Modules\Academic\Livewire\Timetable\Publish;
use Modules\Academic\Livewire\Timetable\Requirements;
use Modules\Academic\Livewire\Timetable\Structures;
use Modules\Academic\Livewire\Timetable\Venues;
use Modules\Academic\Livewire\Timetable\Views;

/**
 * Book E ACA-03 §7 — Timetable & Scheduling Engine admin screens.
 * School-scoped, matching the module's other route files' own
 * convention.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/academic')->name('academic.')->group(function (): void {
    Route::livewire('timetable/structures', Structures::class)->name('timetable.structures');
    Route::livewire('timetable/venues', Venues::class)->name('timetable.venues');
    Route::livewire('timetable/constraints', Constraints::class)->name('timetable.constraints');
    Route::livewire('timetable/requirements', Requirements::class)->name('timetable.requirements');
    Route::livewire('timetable/generate', Generate::class)->name('timetable.generate');
    Route::livewire('timetable/clashes', Clashes::class)->name('timetable.clashes');
    Route::livewire('timetable/views', Views::class)->name('timetable.views');
    Route::livewire('timetable/cover', Cover::class)->name('timetable.cover');
    Route::livewire('timetable/exceptions', Exceptions::class)->name('timetable.exceptions');
    Route::livewire('timetable/exam-planner', ExamPlanner::class)->name('timetable.exam-planner');
    Route::livewire('timetables/{timetable}/editor', Editor::class)->name('timetable.editor');
    Route::livewire('timetables/{timetable}/publish', Publish::class)->name('timetable.publish');
});
