<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Users\Form;
use Modules\Core\Livewire\Users\Index;
use Modules\Core\Livewire\Users\Show;

/**
 * Book A CORE-05 §6 — user directory/identity CRUD. Unlike
 * schools.php/sessions.php, these screens take no `{school}` route
 * parameter: a `User` is a tenant-wide identity (`users.tenant_id` is
 * the only boundary, Book A CORE-05 §2), not a school-scoped record, so
 * there is no `InteractsWithSchool`/`SchoolContext` involved here.
 * `Form` handles both create (`users/create`, no route model) and edit
 * (`users/{user}/edit`, implicitly bound) — see that component's
 * docblock.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('users', Index::class)->name('users.index');
    Route::livewire('users/create', Form::class)->name('users.create');
    Route::livewire('users/{user}/edit', Form::class)->name('users.edit');
    Route::livewire('users/{user}', Show::class)->name('users.show');
});
