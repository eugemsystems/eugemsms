<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Academic\Livewire\Curriculum\Frameworks;
use Modules\Academic\Livewire\Curriculum\Groups;
use Modules\Academic\Livewire\Curriculum\Offerings;
use Modules\Academic\Livewire\Curriculum\Pathways;
use Modules\Academic\Livewire\Curriculum\Prerequisites;
use Modules\Academic\Livewire\Curriculum\SelectionRules;
use Modules\Academic\Livewire\Curriculum\Subjects;
use Modules\Academic\Livewire\Curriculum\Syllabi;

/**
 * Book D ACA-01 §5 — Curriculum, Learning Areas & Pathways admin
 * screens. School-scoped, matching `people/students.php`'s own route
 * convention.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/academic')->name('academic.')->group(function (): void {
    Route::livewire('curriculum/frameworks', Frameworks::class)->name('curriculum.frameworks');
    Route::livewire('curriculum/subjects', Subjects::class)->name('curriculum.subjects');
    Route::livewire('curriculum/groups', Groups::class)->name('curriculum.groups');
    Route::livewire('curriculum/offerings', Offerings::class)->name('curriculum.offerings');
    Route::livewire('curriculum/pathways', Pathways::class)->name('curriculum.pathways');
    Route::livewire('curriculum/selection-rules', SelectionRules::class)->name('curriculum.selection-rules');
    Route::livewire('curriculum/prerequisites', Prerequisites::class)->name('curriculum.prerequisites');
    Route::livewire('curriculum/syllabi', Syllabi::class)->name('curriculum.syllabi');
});
