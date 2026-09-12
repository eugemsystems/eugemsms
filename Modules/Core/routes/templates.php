<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Templates\Editor;
use Modules\Core\Livewire\Templates\Index;
use Modules\Core\Livewire\Templates\Versions;

/**
 * Book A CORE-06 §6 — Document templates. Every screen takes an
 * explicit `{school}` and authorises + sets SchoolContext itself
 * (`InteractsWithSchool`), matching schools.php's/roles.php's own
 * convention. `{templateType}` in the versions route is a plain
 * string, not a model — a template type has no single row of its own,
 * only the versions that share it.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('schools/{school}/templates', Index::class)->name('templates.index');
    Route::livewire('schools/{school}/templates/create', Editor::class)->name('templates.create');
    Route::livewire('schools/{school}/templates/{template}/edit', Editor::class)->name('templates.edit');
    Route::livewire('schools/{school}/templates/versions/{templateType}', Versions::class)->name('templates.versions');
});
