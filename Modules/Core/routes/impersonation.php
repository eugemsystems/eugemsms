<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Users\Impersonate;
use Modules\Core\Livewire\Users\LoginAudit;

/**
 * Book A CORE-05 §6 — the impersonation console and login audit screens.
 * Both are tenant-wide (not `{school}`-scoped) like `routes/web.php`'s
 * `Core\Users\*` screens, so `serp.web`/`SchoolContext` are not involved
 * here — see `Core\Users\Impersonate`/`Core\Users\LoginAudit`'s own
 * docblocks for the authorisation precedent this follows.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('impersonate', Impersonate::class)->name('impersonate.index');
    Route::livewire('login-audit', LoginAudit::class)->name('login-audit.index');
});
