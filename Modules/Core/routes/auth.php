<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Auth\TwoFactorSetup;

/**
 * Book A CORE-05 §6. `Auth\Login` has no route of its own here — Fortify
 * owns `/login` (`FortifyServiceProvider` points its login view at this
 * component; see `Auth\Login`'s own docblock for why the submit itself
 * stays a plain POST through Fortify's pipeline rather than
 * `wire:submit`). This file only carries screens that need a route
 * Fortify doesn't already register.
 */
Route::middleware(['auth'])->group(function (): void {
    Route::livewire('two-factor-setup', TwoFactorSetup::class)->name('two-factor.setup');
});
