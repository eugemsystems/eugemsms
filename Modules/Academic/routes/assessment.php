<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Academic\Livewire\Assessment\Planner;
use Modules\Academic\Livewire\Assessment\Types;
use Modules\Academic\Livewire\Grading\Scales;
use Modules\Academic\Livewire\Marks\Amend;
use Modules\Academic\Livewire\Marks\Entry;
use Modules\Academic\Livewire\Results\Comments;
use Modules\Academic\Livewire\Results\Compute;

/**
 * Book D ACA-05 §6 — Assessment, Grading & Report Cards admin screens
 * (report card generation/publication/transcripts deliberately not
 * built — see `.ai/rules/academic.md`). School-scoped, matching
 * `people/students.php`'s own route convention.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/academic')->name('academic.')->group(function (): void {
    Route::livewire('grading/scales', Scales::class)->name('grading.scales');
    Route::livewire('assessment/types', Types::class)->name('assessment.types');
    Route::livewire('assessment/planner', Planner::class)->name('assessment.planner');

    Route::livewire('assessments/{assessment}/marks', Entry::class)->name('marks.entry');
    Route::livewire('assessments/{assessment}/amend', Amend::class)->name('marks.amend');

    Route::livewire('results/compute', Compute::class)->name('results.compute');
    Route::livewire('results/comments', Comments::class)->name('results.comments');
});
