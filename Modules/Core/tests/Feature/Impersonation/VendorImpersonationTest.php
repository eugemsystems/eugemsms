<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\EndImpersonationAction;
use Modules\Core\Domain\Actions\Auth\GrantSupportAccessAction;
use Modules\Core\Domain\Actions\Auth\RevokeSupportAccessAction;
use Modules\Core\Domain\Actions\Auth\StartVendorImpersonationAction;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\EndImpersonationData;
use Modules\Core\Domain\DataObjects\Auth\GrantSupportAccessData;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\StartVendorImpersonationData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\ImpersonationNotPermittedException;
use Modules\Core\Domain\Exceptions\ImpersonationReadOnlyException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\UserStatus;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Domain\Support\ImpersonationContext;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Livewire\Users\SupportAccess;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\ImpersonationSession;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SupportAccessGrant;
use Modules\Core\Models\Tenant;
use Modules\Saas\Livewire\Vendor\Tenants\Show as TenantShow;

/**
 * Book J SAA-02 BR-SAA-02-002: vendor impersonation is consent-gated by the customer's own
 * time-boxed grant for one ticket, read-only, time-boxed and recorded against that grant.
 *
 * @return array{tenant: Tenant, operator: User, target: User, grant: SupportAccessGrant}
 */
function vendorImpersonationFixture(): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $target = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Staff]);
    $operator = User::factory()->create(['user_type' => UserType::Vendor, 'two_factor_confirmed_at' => now()]);
    $grant = SupportAccessGrant::factory()->create(['tenant_id' => $tenant->id, 'granted_by' => $admin->id, 'ticket_reference' => 'TKT-1001', 'expires_at' => now()->addHours(4)]);

    return compact('tenant', 'operator', 'target', 'grant');
}

function startVendorSession(array $f, array $override = []): ImpersonationSession
{
    $data = array_merge(['operator' => $f['operator']->id, 'target' => $f['target']->id, 'ticket' => 'TKT-1001', 'reason' => 'Look at the bursar statement.'], $override);

    return app(StartVendorImpersonationAction::class)->execute(new StartVendorImpersonationData($data['operator'], $data['target'], $data['ticket'], $data['reason']));
}

it('opens a read-only session under the customer\'s grant and stamps it with that grant', function (): void {
    $f = vendorImpersonationFixture();

    $session = startVendorSession($f);

    expect($session->is_read_only)->toBeTrue()
        ->and($session->access_grant_id)->toBe($f['grant']->id)
        ->and($session->ticket_reference)->toBe('TKT-1001')
        ->and($session->impersonator_id)->toBe($f['operator']->id)
        ->and($session->impersonated_id)->toBe($f['target']->id);
});

it('ends the session when the grant runs out if that is sooner than the platform maximum', function (): void {
    $f = vendorImpersonationFixture();
    $f['grant']->update(['expires_at' => now()->addMinutes(10)]);

    $session = startVendorSession($f);

    expect($session->expires_at->lessThanOrEqualTo(now()->addMinutes(10)->addSecond()))->toBeTrue();
});

it('refuses without an active grant for exactly that ticket', function (string $case): void {
    $f = vendorImpersonationFixture();

    match ($case) {
        'wrong ticket' => $override = ['ticket' => 'TKT-9999'],
        'expired' => $f['grant']->update(['expires_at' => now()->subMinute()]),
        'revoked' => $f['grant']->update(['revoked_at' => now()]),
        'other tenant grant' => $f['grant']->update(['tenant_id' => Tenant::factory()->create()->id]),
    };

    expect(fn () => startVendorSession($f, $override ?? []))->toThrow(ValidationException::class);
    expect(ImpersonationSession::count())->toBe(0);
})->with(['wrong ticket', 'expired', 'revoked', 'other tenant grant']);

it('refuses a non-vendor operator, a vendor target, an inactive target and a missing reason', function (): void {
    $f = vendorImpersonationFixture();
    $staffOperator = User::factory()->create(['tenant_id' => $f['tenant']->id]);

    expect(fn () => startVendorSession($f, ['operator' => $staffOperator->id]))->toThrow(ImpersonationNotPermittedException::class);

    $vendorTarget = User::factory()->create(['user_type' => UserType::Vendor, 'tenant_id' => $f['tenant']->id]);
    expect(fn () => startVendorSession($f, ['target' => $vendorTarget->id]))->toThrow(ValidationException::class);

    $f['target']->update(['status' => UserStatus::Inactive]);
    expect(fn () => startVendorSession($f))->toThrow(ValidationException::class);

    $f['target']->update(['status' => UserStatus::Active]);
    expect(fn () => startVendorSession($f, ['reason' => '  ']))->toThrow(ValidationException::class)
        ->and(ImpersonationSession::count())->toBe(0);
});

it('blocks every write while a read-only session is open, but still lets it end', function (): void {
    $f = vendorImpersonationFixture();
    $session = startVendorSession($f);
    $school = School::factory()->create(['tenant_id' => $f['tenant']->id]);

    ImpersonationContext::set($session);

    expect(fn () => app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'receipt', pattern: 'RCT/{SEQ:6}')))
        ->toThrow(ImpersonationReadOnlyException::class);
    expect($session->fresh()->actions_performed[0]['blocked'])->toBeTrue();

    app(EndImpersonationAction::class)->execute(new EndImpersonationData($session->id));
    expect($session->fresh()->ended_at)->not->toBeNull();
    ImpersonationContext::clear();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'receipt', pattern: 'RCT/{SEQ:6}'));
});

it('leaves an ordinary, non-vendor impersonation session writable as before', function (): void {
    $f = vendorImpersonationFixture();
    $session = ImpersonationSession::create([
        'impersonator_id' => $f['target']->id, 'impersonated_id' => $f['target']->id, 'reason' => 'x', 'ticket_reference' => 'T',
        'started_at' => now(), 'expires_at' => now()->addHour(), 'is_read_only' => false,
    ]);
    $school = School::factory()->create(['tenant_id' => $f['tenant']->id]);
    ImpersonationContext::set($session);

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'receipt', pattern: 'RCT/{SEQ:6}'));

    expect(true)->toBeTrue();
    ImpersonationContext::clear();
});

it('validates a grant and lets only the customer\'s own administrator make or withdraw it', function (): void {
    $f = vendorImpersonationFixture();
    $admin = User::factory()->create(['tenant_id' => $f['tenant']->id]);
    $grantData = fn (array $o = []) => new GrantSupportAccessData(...array_merge(['tenantId' => $f['tenant']->id, 'grantedByUserId' => $admin->id, 'ticketReference' => 'TKT-2', 'reason' => 'Why', 'durationHours' => 4], $o));

    $grant = app(GrantSupportAccessAction::class)->execute($grantData());
    expect($grant->isActive())->toBeTrue()->and($grant->expires_at->between(now()->addHours(3), now()->addHours(5)))->toBeTrue();

    foreach ([['durationHours' => 0], ['durationHours' => 73], ['ticketReference' => ' '], ['reason' => ''], ['tenantId' => Tenant::factory()->create()->id]] as $bad) {
        expect(fn () => app(GrantSupportAccessAction::class)->execute($grantData($bad)))->toThrow(ValidationException::class);
    }
});

it('ends an open session at once when the customer withdraws the grant', function (): void {
    $f = vendorImpersonationFixture();
    $session = startVendorSession($f);

    app(RevokeSupportAccessAction::class)->execute($f['grant']->id, $f['grant']->granted_by);

    expect($f['grant']->fresh()->isActive())->toBeFalse()->and($session->fresh()->ended_at)->not->toBeNull()
        ->and($session->fresh()->isActive())->toBeFalse();
    expect(fn () => startVendorSession($f))->toThrow(ValidationException::class);
});

it('opens a session from the vendor console, swaps the browser to the target and records the audit', function (): void {
    $f = vendorImpersonationFixture();

    Livewire::actingAs($f['operator'])->test(TenantShow::class, ['tenant' => $f['tenant']->id])
        ->set('targetUserId', $f['target']->id)->set('ticketReference', 'TKT-1001')->set('reason', 'Check the statement')
        ->call('impersonate')->assertHasNoErrors()->assertRedirect(route('dashboard'));

    $session = ImpersonationSession::firstOrFail();
    expect(Auth::id())->toBe($f['target']->id)->and(session('impersonator_id'))->toBe($f['operator']->id)
        ->and($session->is_read_only)->toBeTrue()
        ->and(ActivityLogEntry::where('log_name', 'vendor_console')->where('event', 'tenant.impersonation_started')->exists())->toBeTrue();
});

it('shows a vendor console error, not a session, when there is no grant, and refuses anyone who is not vendor staff', function (): void {
    $f = vendorImpersonationFixture();
    $f['grant']->update(['revoked_at' => now()]);

    Livewire::actingAs($f['operator'])->test(TenantShow::class, ['tenant' => $f['tenant']->id])
        ->set('targetUserId', $f['target']->id)->set('ticketReference', 'TKT-1001')->set('reason', 'Check')
        ->call('impersonate')->assertHasErrors(['ticketReference']);
    expect(ImpersonationSession::count())->toBe(0);

    Livewire::actingAs($f['target'])->test(TenantShow::class, ['tenant' => $f['tenant']->id])->assertForbidden();
});

it('lets a customer administrator grant and withdraw access from their own screen, and refuses anyone without the permission', function (): void {
    $school = School::factory()->create();
    SchoolContext::set($school);
    $admin = User::factory()->create(['tenant_id' => $school->tenant_id]);
    $admin->schools()->attach($school, ['status' => 'active', 'is_primary' => true]);
    $viewer = User::factory()->create(['tenant_id' => $school->tenant_id]);
    $viewer->schools()->attach($school, ['status' => 'active', 'is_primary' => true]);

    $permission = Permission::firstOrCreate(['name' => 'core.support_access.manage'], ['guard_name' => 'web', 'module_code' => 'CORE', 'resource' => 'support_access', 'action' => 'manage']);
    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $admin->id, schoolId: $school->id, grants: [new PermissionGrantData($permission->id, PermissionScope::School)],
    ));

    Livewire::actingAs($viewer)->test(SupportAccess::class, ['school' => $school])->assertForbidden();

    $screen = Livewire::actingAs($admin)->test(SupportAccess::class, ['school' => $school])
        ->set('ticketReference', 'TKT-77')->set('reason', 'Statement looks wrong')->set('durationHours', '2')->call('grant')->assertHasNoErrors();

    $grant = SupportAccessGrant::where('ticket_reference', 'TKT-77')->firstOrFail();
    expect($grant->tenant_id)->toBe($school->tenant_id)->and($grant->granted_by)->toBe($admin->id);

    $screen->set('durationHours', '500')->set('ticketReference', 'TKT-78')->call('grant')->assertHasErrors(['durationHours']);
    $screen->call('revoke', $grant->id);
    expect($grant->fresh()->isActive())->toBeFalse();
});
