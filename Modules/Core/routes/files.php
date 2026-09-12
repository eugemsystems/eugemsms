<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Files\AccessLog;
use Modules\Core\Livewire\Files\Categories;
use Modules\Core\Livewire\Files\Index;
use Modules\Core\Livewire\Files\Quota;

/**
 * Book A CORE-10 — File Vault & Media Management. Every screen takes an
 * explicit `{school}` and authorises + sets SchoolContext itself
 * (`InteractsWithSchool`), matching notifications.php's/audit.php's own
 * convention.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('schools/{school}/files', Index::class)->name('files.index');
    Route::livewire('schools/{school}/files/categories', Categories::class)->name('files.categories');
    Route::livewire('schools/{school}/files/access-log', AccessLog::class)->name('files.access-log');
    Route::livewire('schools/{school}/files/quota', Quota::class)->name('files.quota');
});
