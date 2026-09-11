<?php

use App\Models\User;
use Modules\Core\Models\School;

/**
 * Regression guard: `Livewire::test(SomeClass::class)` mounts a
 * component directly and never exercises real routing, so it stayed
 * blind to a bug where every `Route::livewire($uri, SomeClass::class)`
 * route backed by a class literally named `Index` 500'd on a genuine
 * HTTP request with `ComponentNotFoundException` — Livewire's implicit-
 * binding-substitution step re-derives a canonical name from the class
 * and strips the trailing `.index` segment, then fails to resolve that
 * shortened name back to a class without a registered Livewire class
 * location for the module (fixed via `Livewire::addLocation()` in
 * `CoreServiceProvider::boot()`). This test only needs a real HTTP GET,
 * not full coverage of each screen's own behaviour (see their own
 * Livewire-level tests for that).
 */
it('loads every Index-named full-page Livewire route via a real HTTP GET', function (): void {
    $user = User::factory()->create();
    $school = School::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);

    $this->actingAs($user)->get('/schools')->assertOk();
    $this->actingAs($user)->get("/schools/{$school->id}/houses")->assertOk();
    $this->actingAs($user)->get("/schools/{$school->id}/modules")->assertOk();
});
