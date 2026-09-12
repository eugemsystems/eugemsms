<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Numbering\Editor;
use Modules\Core\Livewire\Numbering\GapReport;
use Modules\Core\Livewire\Numbering\Index;

/**
 * Book A CORE-06 §6 — Numbering series. Every screen takes an explicit
 * `{school}` and authorises + sets SchoolContext itself
 * (`InteractsWithSchool`), matching schools.php's/roles.php's own
 * convention.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('schools/{school}/numbering', Index::class)->name('numbering.index');
    Route::livewire('schools/{school}/numbering/create', Editor::class)->name('numbering.create');
    Route::livewire('schools/{school}/numbering/{series}/edit', Editor::class)->name('numbering.edit');
    Route::livewire('schools/{school}/numbering/gap-report', GapReport::class)->name('numbering.gap-report');
});
