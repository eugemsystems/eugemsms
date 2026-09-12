<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Livewire\Users\LoginAudit;
use Modules\Core\Models\LoginAttempt;
use Modules\Core\Models\Tenant;

/**
 * `Core\Users\LoginAudit` (Book A CORE-05 §6/BR-CORE-05-007).
 */
it('lists login attempts for users in the viewer\'s own tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->create(['tenant_id' => $tenant->id]);
    $sameTenantUser = User::factory()->create(['tenant_id' => $tenant->id]);

    LoginAttempt::create([
        'identifier' => $sameTenantUser->email,
        'user_id' => $sameTenantUser->id,
        'guard' => 'web',
        'was_successful' => true,
        'ip_address' => '127.0.0.1',
        'attempted_at' => now(),
    ]);

    Livewire::actingAs($viewer)
        ->test(LoginAudit::class)
        ->assertOk()
        ->assertSee($sameTenantUser->email);
});

it('excludes login attempts belonging to another tenant\'s user', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $viewer = User::factory()->create(['tenant_id' => $tenant->id]);
    $outsider = User::factory()->create(['tenant_id' => $otherTenant->id]);

    LoginAttempt::create([
        'identifier' => $outsider->email,
        'user_id' => $outsider->id,
        'guard' => 'web',
        'was_successful' => true,
        'ip_address' => '10.0.0.1',
        'attempted_at' => now(),
    ]);

    Livewire::actingAs($viewer)
        ->test(LoginAudit::class)
        ->assertDontSee($outsider->email);
});

it('still shows attempts against an identifier that never resolved to a user', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->create(['tenant_id' => $tenant->id]);

    LoginAttempt::create([
        'identifier' => 'nobody@example.test',
        'user_id' => null,
        'guard' => 'web',
        'was_successful' => false,
        'failure_reason' => 'no_such_user',
        'ip_address' => '203.0.113.5',
        'attempted_at' => now(),
    ]);

    Livewire::actingAs($viewer)
        ->test(LoginAudit::class)
        ->assertSee('nobody@example.test')
        ->assertSee('no_such_user');
});

it('filters attempts by success/failure', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    LoginAttempt::create([
        'identifier' => $user->email,
        'user_id' => $user->id,
        'guard' => 'web',
        'was_successful' => true,
        'ip_address' => '127.0.0.1',
        'attempted_at' => now(),
    ]);
    LoginAttempt::create([
        'identifier' => $user->email,
        'user_id' => $user->id,
        'guard' => 'web',
        'was_successful' => false,
        'failure_reason' => 'invalid_password',
        'ip_address' => '127.0.0.1',
        'attempted_at' => now(),
    ]);

    $component = Livewire::actingAs($viewer)->test(LoginAudit::class);

    expect($component->viewData('attempts')->total())->toBe(2);

    $component->set('columnFilters.was_successful', '0');

    expect($component->viewData('attempts')->total())->toBe(1);
    $component->assertSee('invalid_password');
});
