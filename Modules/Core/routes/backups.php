<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Backups\ContractExitExport;
use Modules\Core\Livewire\Backups\Index;
use Modules\Core\Livewire\Backups\Show;

/**
 * Book A CORE-13 — Backup, Restore & Disaster Recovery. `Index`/`Show`
 * are tenant-wide (no `{school}` — see `Index`'s own docblock);
 * `ContractExitExport` is school-scoped, matching files.php's/
 * imports.php's convention for that class of screen. `{backup}` binds
 * by ulid via `HasUlid::getRouteKeyName()`, same as every other model
 * already using that trait.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('backups', Index::class)->name('backups.index');
    Route::livewire('backups/{backup}', Show::class)->name('backups.show');
    Route::livewire('schools/{school}/backups/contract-exit-export', ContractExitExport::class)->name('backups.contract-exit-export');
});
