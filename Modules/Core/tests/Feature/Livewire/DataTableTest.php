<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Livewire\Schools\Index as SchoolsIndex;
use Modules\Core\Models\School;

/**
 * Exercises `Modules\Core\Livewire\Concerns\InteractsWithDataTable`
 * through the one screen currently retrofitted to use it
 * (`Schools\Index`) — a trait has no meaning to test standalone, so
 * this proves the shared component's own behaviour via a real,
 * already-tested vehicle.
 */
function dataTableSchoolsFor(User $user): void
{
    $school1 = School::factory()->create(['code' => 'ALPHA', 'name' => 'Alpha College', 'category' => 'private', 'status' => 'active']);
    $school2 = School::factory()->create(['code' => 'BETA', 'name' => 'Beta Academy', 'category' => 'government', 'status' => 'active']);
    $school3 = School::factory()->create(['code' => 'GAMMA', 'name' => 'Gamma High', 'category' => 'private', 'status' => 'archived']);

    foreach ([$school1, $school2, $school3] as $school) {
        $user->schools()->attach($school, ['is_primary' => false, 'status' => 'active']);
    }
}

it('searches across the configured searchable columns', function (): void {
    $user = User::factory()->create();
    dataTableSchoolsFor($user);

    Livewire::actingAs($user)
        ->test(SchoolsIndex::class)
        ->set('search', 'Beta')
        ->assertSee('Beta Academy')
        ->assertDontSee('Alpha College')
        ->assertDontSee('Gamma High');
});

it('filters by a select-type column filter', function (): void {
    $user = User::factory()->create();
    dataTableSchoolsFor($user);

    Livewire::actingAs($user)
        ->test(SchoolsIndex::class)
        ->set('columnFilters.status', 'archived')
        ->assertSee('Gamma High')
        ->assertDontSee('Alpha College')
        ->assertDontSee('Beta Academy');
});

it('combines the search box and a column filter', function (): void {
    $user = User::factory()->create();
    dataTableSchoolsFor($user);

    Livewire::actingAs($user)
        ->test(SchoolsIndex::class)
        ->set('search', 'a') // matches all three by name/code
        ->set('columnFilters.category', 'private')
        ->assertSee('Alpha College')
        ->assertSee('Gamma High')
        ->assertDontSee('Beta Academy');
});

it('clears the search box, filters, and sort together on resetTableFilters', function (): void {
    $user = User::factory()->create();
    dataTableSchoolsFor($user);

    Livewire::actingAs($user)
        ->test(SchoolsIndex::class)
        ->set('search', 'Beta')
        ->set('columnFilters.status', 'archived')
        ->set('sortColumn', 'name')
        ->call('resetTableFilters')
        ->assertSet('search', '')
        ->assertSet('columnFilters', [])
        ->assertSet('sortColumn', null)
        ->assertSee('Alpha College')
        ->assertSee('Beta Academy')
        ->assertSee('Gamma High');
});

it('toggles sort direction on the same column and resets to ascending on a new one', function (): void {
    $user = User::factory()->create();
    dataTableSchoolsFor($user);

    $component = Livewire::actingAs($user)->test(SchoolsIndex::class);

    $component->call('sortByColumn', 'name')
        ->assertSet('sortColumn', 'name')
        ->assertSet('sortDirection', 'asc');

    $component->call('sortByColumn', 'name')
        ->assertSet('sortDirection', 'desc');

    $component->call('sortByColumn', 'code')
        ->assertSet('sortColumn', 'code')
        ->assertSet('sortDirection', 'asc');
});

it('ignores an out-of-range per-page value and falls back to the default of 10', function (): void {
    $user = User::factory()->create();
    dataTableSchoolsFor($user);

    Livewire::actingAs($user)
        ->test(SchoolsIndex::class)
        ->set('perPage', 999)
        ->assertSet('perPage', 10);
});

it('accepts every documented per-page option', function (): void {
    $user = User::factory()->create();
    dataTableSchoolsFor($user);

    $component = Livewire::actingAs($user)->test(SchoolsIndex::class);

    foreach ([10, 15, 30, 50] as $option) {
        $component->set('perPage', $option)->assertSet('perPage', $option);
    }
});

it('hides a toggled-off column\'s <th> from the rendered table and shows it again once re-toggled', function (): void {
    $user = User::factory()->create();
    dataTableSchoolsFor($user);

    // The "Category" label legitimately still appears elsewhere on the
    // page even while the column is hidden (the column-visibility toggle
    // itself must keep listing it so it can be turned back on) — so the
    // precise, unambiguous check is the <th>'s own wire:key, not whether
    // the word "Category" appears anywhere in the page at all.
    Livewire::actingAs($user)
        ->test(SchoolsIndex::class)
        ->assertSet('hiddenColumns', [])
        ->assertSee('table-header-category', false)
        ->call('toggleColumnVisibility', 'category')
        ->assertSet('hiddenColumns', ['category'])
        ->assertDontSee('table-header-category', false)
        ->call('toggleColumnVisibility', 'category')
        ->assertSet('hiddenColumns', [])
        ->assertSee('table-header-category', false);
});
