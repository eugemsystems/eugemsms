<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Profile\Devices;
use Modules\Core\Livewire\Profile\Security;

/**
 * Book A CORE-05 §6 — `Core\Profile\Devices` / `Core\Profile\Security`.
 * Both screens are "own" scope only: no route parameter, no permission
 * beyond being logged in, and every action they perform is against
 * `Auth::user()`. Unlike `schools.php`/`settings.php` this file never
 * takes a `{school}` — a user's own devices/security settings are not
 * school-scoped data.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('profile/security', Security::class)->name('profile.security');
    Route::livewire('profile/devices', Devices::class)->name('profile.devices');
});
