<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\StartImpersonationAction;
use Modules\Core\Domain\DataObjects\Auth\StartImpersonationData;
use Modules\Core\Livewire\ImpersonationBanner;
use Modules\Core\Livewire\Users\Impersonate;
use Modules\Core\Models\ImpersonationSession;
use Modules\Core\Models\Tenant;

/**
 * `Core\Users\Impersonate` (Book A CORE-05 §6). `ImpersonationTest.php`
 * (Modules/Core/tests/Feature/Auth) already covers `StartImpersonationAction`/
 * `EndImpersonationAction`/`ImpersonationGuard` directly — these tests only
 * cover the screen itself and the session-swap it performs, which has no
 * other coverage anywhere.
 */
function impersonationTenantUsers(): array
{
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();

    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $target = User::factory()->create(['tenant_id' => $tenant->id]);
    $outsider = User::factory()->create(['tenant_id' => $otherTenant->id]);

    return [$admin, $target, $outsider];
}

it('renders the impersonation console for an authenticated user', function (): void {
    [$admin] = impersonationTenantUsers();

    Livewire::actingAs($admin)
        ->test(Impersonate::class)
        ->assertOk()
        ->assertSee('Impersonation console');
});

it('only lists candidate users from the same tenant, excluding the viewer themselves', function (): void {
    [$admin, $target, $outsider] = impersonationTenantUsers();

    $component = Livewire::actingAs($admin)->test(Impersonate::class);

    $candidateIds = $component->instance()->candidateUsers()->pluck('id');

    expect($candidateIds)->toContain($target->id)
        ->and($candidateIds)->not->toContain($outsider->id)
        ->and($candidateIds)->not->toContain($admin->id);
});

it('starts an impersonation session and swaps the authenticated user (BR-CORE-05-017)', function (): void {
    [$admin, $target] = impersonationTenantUsers();

    Livewire::actingAs($admin)
        ->test(Impersonate::class)
        ->set('targetUserId', $target->id)
        ->set('reason', 'Investigating a stuck receipt for support ticket 4821.')
        ->set('ticketReference', 'SUP-4821')
        ->call('start')
        ->assertHasNoErrors();

    expect(Auth::id())->toBe($target->id)
        ->and(session('impersonator_id'))->toBe($admin->id);

    $session = ImpersonationSession::where('impersonator_id', $admin->id)->where('impersonated_id', $target->id)->sole();
    expect($session->isActive())->toBeTrue()
        ->and(session('impersonation_session_id'))->toBe($session->id);
});

it('refuses to start impersonation without a reason', function (): void {
    [$admin, $target] = impersonationTenantUsers();

    Livewire::actingAs($admin)
        ->test(Impersonate::class)
        ->set('targetUserId', $target->id)
        ->set('reason', '')
        ->set('ticketReference', 'SUP-1')
        ->call('start')
        ->assertHasErrors('reason');

    expect(Auth::id())->toBe($admin->id);
    expect(ImpersonationSession::count())->toBe(0);
});

it('refuses to impersonate a user from a different tenant', function (): void {
    [$admin, , $outsider] = impersonationTenantUsers();

    Livewire::actingAs($admin)
        ->test(Impersonate::class)
        ->set('targetUserId', $outsider->id)
        ->set('reason', 'Investigating a stuck receipt.')
        ->set('ticketReference', 'SUP-1')
        ->call('start')
        ->assertHasErrors('targetUserId');

    expect(ImpersonationSession::count())->toBe(0);
});

it('refuses to start a second impersonation session while already impersonating', function (): void {
    [$admin, $target] = impersonationTenantUsers();
    $secondTarget = User::factory()->create(['tenant_id' => $admin->tenant_id]);

    $component = Livewire::actingAs($admin)
        ->test(Impersonate::class)
        ->set('targetUserId', $target->id)
        ->set('reason', 'Investigating a stuck receipt.')
        ->set('ticketReference', 'SUP-1')
        ->call('start');

    expect(Auth::id())->toBe($target->id);

    $component->set('targetUserId', $secondTarget->id)
        ->set('reason', 'Second reason entirely.')
        ->set('ticketReference', 'SUP-2')
        ->call('start');

    expect(Auth::id())->toBe($target->id);
    expect(ImpersonationSession::count())->toBe(1);
});

it('ends an impersonation session and swaps the original admin back in', function (): void {
    [$admin, $target] = impersonationTenantUsers();

    $session = app(StartImpersonationAction::class)->execute(new StartImpersonationData(
        impersonatorId: $admin->id,
        impersonatedId: $target->id,
        reason: 'Investigating a stuck receipt.',
        ticketReference: 'SUP-1',
    ));

    session(['impersonator_id' => $admin->id, 'impersonation_session_id' => $session->id]);
    Auth::login($target);

    Livewire::actingAs($target)
        ->test(Impersonate::class)
        ->call('end', $session->id);

    expect(Auth::id())->toBe($admin->id)
        ->and(session('impersonator_id'))->toBeNull()
        ->and(session('impersonation_session_id'))->toBeNull();

    expect($session->fresh()->isActive())->toBeFalse();
});

it('requires a consent reference to start an impersonation session in production', function (): void {
    [$admin, $target] = impersonationTenantUsers();

    app()->detectEnvironment(fn (): string => 'production');

    Livewire::actingAs($admin)
        ->test(Impersonate::class)
        ->set('targetUserId', $target->id)
        ->set('reason', 'Investigating a stuck receipt.')
        ->set('ticketReference', 'SUP-1')
        ->set('consentReference', '')
        ->call('start')
        ->assertHasErrors('consentReference');

    expect(ImpersonationSession::count())->toBe(0);
});

it('shows the stop-impersonating banner only while impersonating, and reverses the swap on stop', function (): void {
    [$admin, $target] = impersonationTenantUsers();

    $session = app(StartImpersonationAction::class)->execute(new StartImpersonationData(
        impersonatorId: $admin->id,
        impersonatedId: $target->id,
        reason: 'Investigating a stuck receipt.',
        ticketReference: 'SUP-1',
    ));

    Livewire::actingAs($admin)
        ->test(ImpersonationBanner::class)
        ->assertDontSee('Stop impersonating');

    session(['impersonator_id' => $admin->id, 'impersonation_session_id' => $session->id]);
    Auth::login($target);

    Livewire::actingAs($target)
        ->test(ImpersonationBanner::class)
        ->assertSee('Stop impersonating')
        ->call('stop');

    expect(Auth::id())->toBe($admin->id)
        ->and(session('impersonator_id'))->toBeNull();

    expect($session->fresh()->isActive())->toBeFalse();
});
