<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Livewire\Users\Form;
use Modules\Core\Models\School;

/**
 * 2026-09-12, user-requested: "users must not be able to guess numbers
 * on the url". Every business table already carried a `ulid` column
 * (Book A §0.3/§0.4, BR-GLOBAL-040), but nothing ever actually resolved
 * routes by it until `HasUlid::getRouteKeyName()` was added — every URL
 * still showed the sequential integer id in the meantime. This locks in
 * both halves: the id no longer works, and the ulid does.
 */
it('resolves a school by ulid, not by its sequential id', function (): void {
    $user = User::factory()->create();
    $school = School::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);

    $this->actingAs($user)->get("/schools/{$school->id}/houses")->assertNotFound();
    $this->actingAs($user)->get("/schools/{$school->ulid}/houses")->assertOk();
});

it('resolves a user by ulid, not by its sequential id, and generates urls with the ulid', function (): void {
    $admin = User::factory()->create();
    $target = User::factory()->create(['tenant_id' => $admin->tenant_id]);

    expect(route('users.show', $target))->toContain($target->ulid)
        ->and(route('users.show', $target))->not->toContain("/users/{$target->id}");

    $this->actingAs($admin)->get("/users/{$target->id}")->assertNotFound();
    $this->actingAs($admin)->get("/users/{$target->ulid}")->assertOk();
});

it('links the sidebar\'s school-scoped nav items by ulid, not by the raw resolved school id (2026-09-12 bugfix)', function (): void {
    $user = User::factory()->create();
    $school = School::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()
        ->assertSee("/schools/{$school->ulid}/sessions/years", false)
        ->assertSee("/schools/{$school->ulid}/settings", false)
        ->assertSee("/schools/{$school->ulid}/roles", false)
        ->assertSee("/schools/{$school->ulid}/permissions", false)
        ->assertDontSee("/schools/{$school->id}/sessions/years", false)
        ->assertDontSee("/schools/{$school->id}/settings", false)
        ->assertDontSee("/schools/{$school->id}/roles", false)
        ->assertDontSee("/schools/{$school->id}/permissions", false);
});

it('redirects to the ulid-based show url after creating a user from the form (2026-09-12 bugfix)', function (): void {
    $admin = User::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('firstName', 'Rudo')
        ->set('lastName', 'Chuma')
        ->set('email', 'rudo-ulid@example.com')
        ->set('userType', 'staff')
        ->call('save')
        ->assertRedirect(route('users.show', User::where('email', 'rudo-ulid@example.com')->sole()));
});

it('links the "back" button on the user form by ulid, not by the raw id (2026-09-12 bugfix)', function (): void {
    $admin = User::factory()->create();
    $target = User::factory()->create(['tenant_id' => $admin->tenant_id]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['user' => $target])
        ->assertSee(route('users.show', $target), false)
        ->assertDontSee("/users/{$target->id}\"", false);
});
