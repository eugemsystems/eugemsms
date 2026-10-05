<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\FeatureFlag;
use Modules\Core\Models\FeatureFlagOverride;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\Actions\GetAnnouncementsForTenantAction;
use Modules\Saas\Domain\Actions\OpenIncidentAction;
use Modules\Saas\Domain\Actions\PostIncidentUpdateAction;
use Modules\Saas\Domain\DataObjects\OpenIncidentData;
use Modules\Saas\Livewire\Tenant\Announcements;
use Modules\Saas\Livewire\Vendor\Broadcasts\Compose;
use Modules\Saas\Livewire\Vendor\Incidents\Manage as IncidentsManage;
use Modules\Saas\Livewire\Vendor\Releases\Index as ReleasesIndex;
use Modules\Saas\Livewire\Vendor\Rollouts\Index as RolloutsIndex;
use Modules\Saas\Livewire\Vendor\Tenants\Index as TenantsIndex;
use Modules\Saas\Livewire\Vendor\Tenants\Show as TenantsShow;
use Modules\Saas\Models\BroadcastAnnouncement;
use Modules\Saas\Models\FeatureRollout;
use Modules\Saas\Models\IncidentStatusEntry;
use Modules\Saas\Models\ReleaseDeployment;
use Modules\Saas\Models\SupportTicket;
use Modules\Saas\Models\TenantHealthSnapshot;

/**
 * Book J SAA-02 admin-UI pass. Own, distinctly-named helpers.
 */
function saa02AdminVendor(): User
{
    return User::factory()->create(['user_type' => UserType::Vendor, 'two_factor_confirmed_at' => now()]);
}

it('refuses every SAA-02 vendor component to a school user (AC-SAA-02-001)', function (string $component, array $params): void {
    $school = School::factory()->create();
    SchoolContext::set($school);
    $schoolAdmin = User::factory()->create(['tenant_id' => $school->tenant_id]);

    Livewire::actingAs($schoolAdmin)->test($component, $params ? ['tenant' => Tenant::factory()->create()->id] : [])->assertForbidden();
})->with([
    'tenants' => [TenantsIndex::class, []],
    'tenant detail' => [TenantsShow::class, ['t']],
    'rollouts' => [RolloutsIndex::class, []],
    'releases' => [ReleasesIndex::class, []],
    'broadcasts' => [Compose::class, []],
    'incidents' => [IncidentsManage::class, []],
]);

it('shows a tenant’s health score with every component signal beside it (AC-SAA-02-004)', function (): void {
    $vendor = saa02AdminVendor();
    $tenant = Tenant::factory()->create(['name' => 'Greenfield Group']);
    TenantHealthSnapshot::factory()->create(['tenant_id' => $tenant->id, 'snapshot_date' => today(), 'health_score' => 62, 'last_login_days_ago' => 31, 'module_adoption_percent' => 40, 'open_support_tickets' => 4, 'integrity_check_failures' => 1]);

    Livewire::actingAs($vendor)->test(TenantsIndex::class)->assertSee('Greenfield Group')->assertSee('62')->assertSee('31')->assertSee('40');

    Livewire::actingAs($vendor)->test(TenantsShow::class, ['tenant' => $tenant->id])
        ->assertSee('Days since last login')->assertSee('Module adoption')->assertSee('Open support tickets')->assertSee('Integrity check failures');
});

it('audits every view and action against a tenant (BR-SAA-02-007) and reads its tickets across schools', function (): void {
    $vendor = saa02AdminVendor();
    $tenant = Tenant::factory()->create();
    $school = School::factory()->create(['tenant_id' => $tenant->id]);
    SupportTicket::factory()->create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'subject' => 'Cannot print report cards']);

    Livewire::actingAs($vendor)->test(TenantsShow::class, ['tenant' => $tenant->id])->assertSee('Cannot print report cards')->call('recompute');

    $events = ActivityLogEntry::where('log_name', 'vendor_console')->where('subject_id', $tenant->id)->pluck('event')->all();

    expect($events)->toContain('tenant.viewed', 'tenant.health_recomputed');
});

it('starts a pilot visible to the named tenants only and advances one stage at a time (AC-SAA-02-002)', function (): void {
    $vendor = saa02AdminVendor();
    $pilot = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $flag = FeatureFlag::factory()->create(['key' => 'new_timetable', 'is_globally_enabled' => false]);

    $component = Livewire::actingAs($vendor)->test(RolloutsIndex::class)
        ->set('flagKey', 'new_timetable')->set('pilotTenantIds', [$pilot->id])->call('start')->assertHasNoErrors();

    $rollout = FeatureRollout::firstOrFail();

    expect($rollout->rollout_stage)->toBe('pilot')
        ->and(FeatureFlagOverride::where('feature_flag_id', $flag->id)->where('scope_id', $pilot->id)->exists())->toBeTrue()
        ->and(FeatureFlagOverride::where('feature_flag_id', $flag->id)->where('scope_id', $other->id)->exists())->toBeFalse()
        ->and($flag->fresh()->is_globally_enabled)->toBeFalse();

    $component->call('beginAdvance', $rollout->id)->set('cohortTenantIds', [])->call('advance')->assertHasErrors('advancingId');
    expect($rollout->fresh()->rollout_stage)->toBe('pilot');

    $component->set('cohortTenantIds', [$other->id])->call('advance')->assertHasNoErrors();
    expect($rollout->fresh()->rollout_stage)->toBe('cohort');

    $component->call('beginAdvance', $rollout->id)->set('percentage', 100)->call('advance')->assertHasErrors('advancingId');
    expect($rollout->fresh()->rollout_stage)->toBe('cohort');
});

it('refuses a pilot with no or unknown tenants, an already-global feature, or a second concurrent rollout', function (): void {
    $vendor = saa02AdminVendor();
    $tenant = Tenant::factory()->create();
    FeatureFlag::factory()->create(['key' => 'on_for_all', 'is_globally_enabled' => true]);
    FeatureFlag::factory()->create(['key' => 'staged', 'is_globally_enabled' => false]);

    $component = Livewire::actingAs($vendor)->test(RolloutsIndex::class);

    $component->set('flagKey', 'staged')->set('pilotTenantIds', [999999])->call('start')->assertHasErrors('flagKey');
    $component->set('flagKey', 'on_for_all')->set('pilotTenantIds', [$tenant->id])->call('start')->assertHasErrors('flagKey');

    $component->set('flagKey', 'staged')->set('pilotTenantIds', [$tenant->id])->call('start')->assertHasNoErrors();
    $component->set('flagKey', 'staged')->set('pilotTenantIds', [$tenant->id])->call('start')->assertHasErrors('flagKey');

    expect(FeatureRollout::count())->toBe(1);
});

it('keeps rollback available until a canary is confirmed stable, then closes it (AC-SAA-02-003)', function (): void {
    $vendor = saa02AdminVendor();
    $canary = Tenant::factory()->create();

    $component = Livewire::actingAs($vendor)->test(ReleasesIndex::class)->set('version', '1.4.0')->set('canaryTenantIds', [$canary->id])->call('startCanary')->assertHasNoErrors();
    $release = ReleaseDeployment::firstOrFail();

    expect($release->rollback_available)->toBeTrue()->and($release->canary_tenant_ids)->toBe([$canary->id]);

    $component->call('confirmStable', $release->id);
    expect($release->fresh())->rollback_available->toBeFalse()->deployment_stage->toBe('general');

    $component->call('rollback', $release->id);
    expect($release->fresh()->migration_status)->toBe('completed');
});

it('will not confirm a rolled-back release stable, roll one back twice, or reuse a version', function (): void {
    $vendor = saa02AdminVendor();
    $canary = Tenant::factory()->create();

    $component = Livewire::actingAs($vendor)->test(ReleasesIndex::class)->set('version', '2.0.0')->set('canaryTenantIds', [$canary->id])->call('startCanary');
    $release = ReleaseDeployment::firstOrFail();

    $component->call('rollback', $release->id);
    expect($release->fresh()->migration_status)->toBe('rolled_back');

    $component->call('confirmStable', $release->id);
    expect($release->fresh())->rollback_available->toBeTrue()->migration_status->toBe('rolled_back');

    $component->set('version', '2.0.0')->set('canaryTenantIds', [$canary->id])->call('startCanary')->assertHasErrors('version');
    $component->set('version', 'v-latest')->set('canaryTenantIds', [$canary->id])->call('startCanary')->assertHasErrors('version');

    expect(ReleaseDeployment::count())->toBe(1);
});

it('addresses a broadcast explicitly and never widens an empty selection to everyone (BR-SAA-02-006)', function (): void {
    $vendor = saa02AdminVendor();
    $targeted = Tenant::factory()->create();
    $other = Tenant::factory()->create();

    $component = Livewire::actingAs($vendor)->test(Compose::class)->set('title', 'Maintenance tonight')->set('body', 'Brief downtime at 22:00.')->set('severity', 'maintenance');

    $component->set('audience', 'selected')->set('tenantIds', [])->call('post')->assertHasErrors('audience');
    expect(BroadcastAnnouncement::count())->toBe(0);

    $component->set('tenantIds', [$targeted->id])->call('post')->assertHasNoErrors();

    $targetedFeed = app(GetAnnouncementsForTenantAction::class)->execute($targeted->id);
    $otherFeed = app(GetAnnouncementsForTenantAction::class)->execute($other->id);

    expect($targetedFeed)->toHaveCount(1)->and($otherFeed)->toHaveCount(0);

    $component->set('title', 'Everyone')->set('body', 'Hello all.')->set('audience', 'all')->call('post')->assertHasNoErrors();
    expect(app(GetAnnouncementsForTenantAction::class)->execute($other->id))->toHaveCount(1);
});

it('refuses a broadcast with a bad severity or an end before its start', function (): void {
    $vendor = saa02AdminVendor();

    Livewire::actingAs($vendor)->test(Compose::class)->set('title', 'T')->set('body', 'B')->set('audience', 'all')->set('severity', 'spam')->call('post')->assertHasErrors('severity');

    Livewire::actingAs($vendor)->test(Compose::class)->set('title', 'T')->set('body', 'B')->set('audience', 'all')->set('startsAt', now()->addDay()->toDateTimeString())->set('endsAt', now()->toDateTimeString())->call('post')->assertHasErrors('audience');

    expect(BroadcastAnnouncement::count())->toBe(0);
});

it('shows a tenant its own announcements only, and refuses another tenant’s user (BR-SAA-02-006)', function (): void {
    $tenant = Tenant::factory()->create();
    $school = School::factory()->create(['tenant_id' => $tenant->id]);
    SchoolContext::set($school);
    BroadcastAnnouncement::factory()->create(['title' => 'For tenant A only', 'target_tenant_ids' => [$tenant->id], 'starts_at' => now()->subHour(), 'ends_at' => null]);
    BroadcastAnnouncement::factory()->create(['title' => 'For somebody else', 'target_tenant_ids' => [Tenant::factory()->create()->id], 'starts_at' => now()->subHour(), 'ends_at' => null]);

    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->schools()->attach($school, ['status' => 'active']);
    grantSaa02View($admin, $school);

    Livewire::actingAs($admin)->test(Announcements::class, ['school' => $school])->assertSee('For tenant A only')->assertDontSee('For somebody else');

    $stranger = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $stranger->schools()->attach($school, ['status' => 'active']);
    grantSaa02View($stranger, $school);

    Livewire::actingAs($stranger)->test(Announcements::class, ['school' => $school])->assertForbidden();
});

function grantSaa02View(User $user, School $school): void
{
    $permission = Permission::firstOrCreate(['name' => 'subscription.view'], ['guard_name' => 'web', 'module_code' => 'SUBSCRIPTION', 'resource' => 'view', 'action' => 'view']);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $school->id, grants: [new PermissionGrantData($permission->id, PermissionScope::School)],
    ));
}

it('appends incident updates, closes a resolved incident, and keeps private ones off the public page', function (): void {
    $vendor = saa02AdminVendor();

    $component = Livewire::actingAs($vendor)->test(IncidentsManage::class)
        ->set('title', 'Payments slow')->set('components', 'payments, fiscalisation')->set('severity', 'major')->set('message', 'Investigating.')->call('open')->assertHasNoErrors()
        ->set('title', 'Internal DB failover test')->set('components', 'database')->set('message', 'Planned.')->set('isPublic', false)->call('open')->assertHasNoErrors();

    $public = IncidentStatusEntry::where('title', 'Payments slow')->firstOrFail();

    $component->call('beginUpdate', $public->id)->set('updateStatus', 'resolved')->set('updateMessage', 'Recovered.')->call('postUpdate')->assertHasNoErrors();
    expect($public->fresh())->status->toBe('resolved')->and($public->fresh()->updates)->toHaveCount(2);

    expect(fn () => $component->call('beginUpdate', $public->id))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(PostIncidentUpdateAction::class)->execute($public->id, 'monitoring', 'again'))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(OpenIncidentAction::class)->execute(new OpenIncidentData('X', [], 'minor', 'm')))->toThrow(InvalidArgumentException::class);

    auth()->logout();
    $this->get('/status')->assertOk()->assertSee('Payments slow')->assertDontSee('Internal DB failover test');
});
