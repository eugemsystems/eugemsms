<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Permissions\Explorer as PermissionsExplorer;
use Modules\Core\Livewire\Roles\Editor as RolesEditor;
use Modules\Core\Livewire\Roles\Index as RolesIndex;
use Modules\Core\Livewire\Users\DirectPermissions;

/**
 * Book A CORE-05 §6 — Roles & Permissions. Roles are per-school
 * (`roles.school_id`, BR-CORE-05-012), so every screen here takes an
 * explicit `{school}` and authorises + sets SchoolContext itself
 * (`InteractsWithSchool`), matching schools.php/settings.php's
 * convention rather than the tenant-wide Users screens.
 *
 * `users/{user}/permissions` lives here rather than in users.php despite
 * taking a `{user}` — it's a permission-editing screen (direct grants,
 * not role grants), scoped to a school for the same reason Roles/
 * Permissions are, so it belongs with them rather than with the
 * tenant-wide Users screens.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('schools/{school}/roles', RolesIndex::class)->name('roles.index');
    Route::livewire('schools/{school}/roles/{role}/edit', RolesEditor::class)->name('roles.edit');
    Route::livewire('schools/{school}/permissions', PermissionsExplorer::class)->name('permissions.explorer');
    Route::livewire('schools/{school}/users/{user}/permissions', DirectPermissions::class)->name('users.permissions');
});
