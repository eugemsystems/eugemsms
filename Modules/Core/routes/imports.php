<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Imports\Batch;
use Modules\Core\Livewire\Imports\History;
use Modules\Core\Livewire\Imports\Index;
use Modules\Core\Livewire\Imports\Mapper;

/**
 * Book A CORE-11 — Data Import & Migration Toolkit. Every screen takes
 * an explicit `{school}` and authorises + sets SchoolContext itself
 * (`InteractsWithSchool`), matching files.php's/notifications.php's own
 * convention. `{batch}` binds by ulid via `HasUlid::getRouteKeyName()`,
 * same as every other model already using that trait.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('schools/{school}/imports', Index::class)->name('imports.index');
    Route::livewire('schools/{school}/imports/history', History::class)->name('imports.history');
    Route::livewire('schools/{school}/imports/batches/{batch}', Batch::class)->name('imports.batches.show');
    Route::livewire('schools/{school}/imports/{definitionKey}/new', Mapper::class)->name('imports.mapper');
});
