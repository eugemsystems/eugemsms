<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Exceptions\TwoFactorRequiredException;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Http\Middleware\EnsureVendorGuard;
use Modules\Core\Models\FeatureFlag;
use Modules\Core\Models\FeatureFlagOverride;
use Modules\Core\Models\IntegrityCheckRun;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\People\Models\Student;
use Modules\Saas\Domain\Actions\AdvanceFeatureRolloutStageAction;
use Modules\Saas\Domain\Actions\ComposeBroadcastAnnouncementAction;
use Modules\Saas\Domain\Actions\ComputeTenantHealthSnapshotAction;
use Modules\Saas\Domain\Actions\ConfirmReleaseStableAction;
use Modules\Saas\Domain\Actions\CreateFeatureRolloutAction;
use Modules\Saas\Domain\Actions\OpenIncidentAction;
use Modules\Saas\Domain\Actions\PostIncidentUpdateAction;
use Modules\Saas\Domain\Actions\RollbackReleaseAction;
use Modules\Saas\Domain\Actions\StartCanaryReleaseAction;
use Modules\Saas\Domain\DataObjects\AdvanceFeatureRolloutStageData;
use Modules\Saas\Domain\DataObjects\ComposeBroadcastAnnouncementData;
use Modules\Saas\Domain\DataObjects\CreateFeatureRolloutData;
use Modules\Saas\Domain\DataObjects\OpenIncidentData;
use Modules\Saas\Domain\DataObjects\StartCanaryReleaseData;
use Modules\Saas\Domain\Exceptions\FeatureRolloutAlreadyAtFinalStageException;
use Modules\Saas\Domain\Exceptions\RollbackNoLongerAvailableException;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\SupportTicket;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('computes a tenant health snapshot from real, decomposed components (BR-SAA-02-003/AC-SAA-02-004)', function (): void {
    $tenant = Tenant::factory()->create();
    $school = School::factory()->for($tenant)->create(['status' => 'active']);
    Student::factory()->count(3)->create(['school_id' => $school->id, 'status' => 'active']);
    Subscription::factory()->for($tenant)->create(['status' => 'active']);
    SupportTicket::factory()->create(['tenant_id' => $tenant->id, 'status' => 'open']);
    IntegrityCheckRun::create([
        'school_id' => $school->id, 'check_type' => 'trial_balance', 'status' => 'failed',
        'records_checked' => 10, 'failures_found' => 2, 'failure_details' => [], 'duration_ms' => 5, 'ran_at' => Carbon::now(),
    ]);

    $snapshot = app(ComputeTenantHealthSnapshotAction::class)->execute($tenant->id);

    expect($snapshot->active_schools)->toBe(1)
        ->and($snapshot->active_learners)->toBe(3)
        ->and($snapshot->subscription_status)->toBe('active')
        ->and($snapshot->open_support_tickets)->toBe(1)
        ->and($snapshot->integrity_check_failures)->toBe(2)
        ->and($snapshot->health_score)->not->toBeNull()
        ->and($snapshot->health_score)->toBeLessThan(100.0);
});

it('refuses a school-level user at the vendor guard, whatever their role (AC-SAA-02-001)', function (): void {
    $schoolUser = User::factory()->create(['user_type' => UserType::Staff]);
    $request = Request::create('/vendor/tenants', 'GET');
    $request->setUserResolver(fn () => $schoolUser);

    expect(fn () => (new EnsureVendorGuard)->handle($request, fn () => response('ok')))
        ->toThrow(HttpException::class);
});

it('requires two-factor authentication for a vendor user at the guard', function (): void {
    $vendorUser = User::factory()->create(['user_type' => UserType::Vendor, 'two_factor_confirmed_at' => null]);
    $request = Request::create('/vendor/tenants', 'GET');
    $request->setUserResolver(fn () => $vendorUser);

    expect(fn () => (new EnsureVendorGuard)->handle($request, fn () => response('ok')))
        ->toThrow(TwoFactorRequiredException::class);
});

it('admits a two-factor-confirmed vendor user through the guard', function (): void {
    $vendorUser = User::factory()->create(['user_type' => UserType::Vendor, 'two_factor_confirmed_at' => Carbon::now()]);
    $request = Request::create('/vendor/tenants', 'GET');
    $request->setUserResolver(fn () => $vendorUser);

    $response = (new EnsureVendorGuard)->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('enforces the vendor IP allowlist once configured', function (): void {
    config(['services.vendor.ip_allowlist' => ['203.0.113.5']]);

    $vendorUser = User::factory()->create(['user_type' => UserType::Vendor, 'two_factor_confirmed_at' => Carbon::now()]);
    $request = Request::create('/vendor/tenants', 'GET', server: ['REMOTE_ADDR' => '198.51.100.9']);
    $request->setUserResolver(fn () => $vendorUser);

    expect(fn () => (new EnsureVendorGuard)->handle($request, fn () => response('ok')))
        ->toThrow(HttpException::class);
});

it('starts a pilot rollout visible only to named tenants and advances one explicit stage at a time (BR-SAA-02-004/AC-SAA-02-002)', function (): void {
    $flag = FeatureFlag::factory()->create();
    $pilotTenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $vendorUser = User::factory()->create();

    $rollout = app(CreateFeatureRolloutAction::class)->execute(new CreateFeatureRolloutData(
        featureFlagKey: $flag->key,
        pilotTenantIds: [$pilotTenant->id],
        startedBy: $vendorUser->id,
    ));

    expect($rollout->rollout_stage)->toBe('pilot')
        ->and(FeatureFlagOverride::where('feature_flag_id', $flag->id)->where('scope_type', 'tenant')->where('scope_id', $pilotTenant->id)->first()?->is_enabled)->toBeTrue()
        ->and(FeatureFlagOverride::where('feature_flag_id', $flag->id)->where('scope_type', 'tenant')->where('scope_id', $otherTenant->id)->exists())->toBeFalse();

    $cohortTenant = Tenant::factory()->create();
    $advanced = app(AdvanceFeatureRolloutStageAction::class)->execute(new AdvanceFeatureRolloutStageData(
        rolloutId: $rollout->id,
        cohortTenantIds: [$pilotTenant->id, $cohortTenant->id],
    ));

    expect($advanced->rollout_stage)->toBe('cohort')
        ->and(FeatureFlagOverride::where('feature_flag_id', $flag->id)->where('scope_type', 'tenant')->where('scope_id', $cohortTenant->id)->first()?->is_enabled)->toBeTrue();

    $percentageStage = app(AdvanceFeatureRolloutStageAction::class)->execute(new AdvanceFeatureRolloutStageData(rolloutId: $rollout->id, percentage: 25));
    expect($percentageStage->rollout_stage)->toBe('percentage')->and($flag->fresh()->rollout_percentage)->toBe(25);

    $general = app(AdvanceFeatureRolloutStageAction::class)->execute(new AdvanceFeatureRolloutStageData(rolloutId: $rollout->id));
    expect($general->rollout_stage)->toBe('general')->and($flag->fresh()->is_globally_enabled)->toBeTrue();

    expect(fn () => app(AdvanceFeatureRolloutStageAction::class)->execute(new AdvanceFeatureRolloutStageData(rolloutId: $rollout->id)))
        ->toThrow(FeatureRolloutAlreadyAtFinalStageException::class);
});

it('keeps rollback available for a canary release until it is explicitly confirmed stable (BR-SAA-02-005/AC-SAA-02-003)', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $deployment = app(StartCanaryReleaseAction::class)->execute(new StartCanaryReleaseData(
        version: '2.4.0',
        canaryTenantIds: [$tenantA->id, $tenantB->id],
    ));

    expect($deployment->deployment_stage)->toBe('canary')->and($deployment->rollback_available)->toBeTrue();

    $rolledBack = app(RollbackReleaseAction::class)->execute($deployment->id);
    expect($rolledBack->migration_status)->toBe('rolled_back');

    $again = app(StartCanaryReleaseAction::class)->execute(new StartCanaryReleaseData(version: '2.4.1', canaryTenantIds: [$tenantA->id]));
    app(ConfirmReleaseStableAction::class)->execute($again->id);

    expect(fn () => app(RollbackReleaseAction::class)->execute($again->id))
        ->toThrow(RollbackNoLongerAvailableException::class);
});

it('targets a broadcast announcement explicitly, never inferred (BR-SAA-02-006)', function (): void {
    $targetedTenant = Tenant::factory()->create();
    $untargetedTenant = Tenant::factory()->create();
    $vendorUser = User::factory()->create();

    $announcement = app(ComposeBroadcastAnnouncementAction::class)->execute(new ComposeBroadcastAnnouncementData(
        title: 'Scheduled maintenance',
        body: 'The platform will be briefly unavailable tonight.',
        severity: 'maintenance',
        postedBy: $vendorUser->id,
        targetTenantIds: [$targetedTenant->id],
    ));

    expect($announcement->targets($targetedTenant->id))->toBeTrue()
        ->and($announcement->targets($untargetedTenant->id))->toBeFalse();

    $everyone = app(ComposeBroadcastAnnouncementAction::class)->execute(new ComposeBroadcastAnnouncementData(
        title: 'Platform-wide notice', body: 'Something for everyone.', severity: 'info', postedBy: $vendorUser->id,
    ));

    expect($everyone->targets($untargetedTenant->id))->toBeTrue();
});

it('appends incident updates to the timeline rather than overwriting it', function (): void {
    $incident = app(OpenIncidentAction::class)->execute(new OpenIncidentData(
        title: 'Elevated payment gateway latency',
        affectedComponents: ['payments'],
        severity: 'minor',
        initialMessage: 'Investigating reports of slow checkout.',
    ));

    expect($incident->status)->toBe('investigating')->and($incident->updates)->toHaveCount(1);

    $identified = app(PostIncidentUpdateAction::class)->execute($incident->id, 'identified', 'Root cause found in the gateway provider.');
    $resolved = app(PostIncidentUpdateAction::class)->execute($incident->id, 'resolved', 'Gateway provider confirms full recovery.');

    expect($identified->updates)->toHaveCount(2)
        ->and($resolved->status)->toBe('resolved')
        ->and($resolved->updates)->toHaveCount(3);
});
