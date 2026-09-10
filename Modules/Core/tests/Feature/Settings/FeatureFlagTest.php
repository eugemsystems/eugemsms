<?php

use Modules\Core\Domain\Actions\Settings\ToggleFeatureFlagAction;
use Modules\Core\Domain\DataObjects\Settings\ToggleFeatureFlagData;
use Modules\Core\Domain\Support\Settings\FeatureFlagResolver;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Models\FeatureFlag;
use Modules\Core\Models\FeatureFlagOverride;

it('returns false for an unknown flag', function (): void {
    expect(app(FeatureFlagResolver::class)->isEnabled('nonexistent'))->toBeFalse();
});

it('falls back to the global default', function (): void {
    FeatureFlag::factory()->create(['key' => 'new-report-card', 'is_globally_enabled' => true]);

    expect(app(FeatureFlagResolver::class)->isEnabled('new-report-card', new ScopeChain))->toBeTrue();
});

it('resolution order: user override beats school beats tenant beats global (BR-CORE-04-016)', function (): void {
    $flag = FeatureFlag::factory()->create(['key' => 'beta-feature', 'is_globally_enabled' => true]);

    FeatureFlagOverride::factory()->for($flag, 'featureFlag')->create(['scope_type' => 'tenant', 'scope_id' => 1, 'is_enabled' => false]);
    FeatureFlagOverride::factory()->for($flag, 'featureFlag')->create(['scope_type' => 'school', 'scope_id' => 1, 'is_enabled' => true]);
    FeatureFlagOverride::factory()->for($flag, 'featureFlag')->create(['scope_type' => 'user', 'scope_id' => 1, 'is_enabled' => false]);

    $resolver = app(FeatureFlagResolver::class);

    expect($resolver->isEnabled('beta-feature', new ScopeChain(userId: 1, schoolId: 1, tenantId: 1)))->toBeFalse()
        ->and($resolver->isEnabled('beta-feature', new ScopeChain(schoolId: 1, tenantId: 1)))->toBeTrue()
        ->and($resolver->isEnabled('beta-feature', new ScopeChain(tenantId: 1)))->toBeFalse()
        ->and($resolver->isEnabled('beta-feature', new ScopeChain))->toBeTrue();
});

it('toggling the global default persists', function (): void {
    FeatureFlag::factory()->create(['key' => 'my-flag', 'is_globally_enabled' => false]);

    app(ToggleFeatureFlagAction::class)->execute(new ToggleFeatureFlagData('my-flag', true));

    expect(app(FeatureFlagResolver::class)->isEnabled('my-flag', new ScopeChain))->toBeTrue();
});

it('toggling a school override persists and is scoped', function (): void {
    FeatureFlag::factory()->create(['key' => 'my-flag', 'is_globally_enabled' => false]);

    app(ToggleFeatureFlagAction::class)->execute(new ToggleFeatureFlagData('my-flag', true, 'school', 5));

    $resolver = app(FeatureFlagResolver::class);
    expect($resolver->isEnabled('my-flag', new ScopeChain(schoolId: 5)))->toBeTrue()
        ->and($resolver->isEnabled('my-flag', new ScopeChain(schoolId: 6)))->toBeFalse();
});

it('rollout percentage is stable for the same school', function (): void {
    $flag = FeatureFlag::factory()->create(['key' => 'rollout-flag', 'is_globally_enabled' => false, 'rollout_percentage' => 100]);

    $resolver = app(FeatureFlagResolver::class);
    $first = $resolver->isEnabled('rollout-flag', new ScopeChain(schoolId: 42));
    $second = $resolver->isEnabled('rollout-flag', new ScopeChain(schoolId: 42));

    expect($first)->toBe($second)->and($first)->toBeTrue();
});

it('a zero-percent rollout with no override reports disabled', function (): void {
    FeatureFlag::factory()->create(['key' => 'off-flag', 'is_globally_enabled' => false, 'rollout_percentage' => 0]);

    expect(app(FeatureFlagResolver::class)->isEnabled('off-flag', new ScopeChain(schoolId: 42)))->toBeFalse();
});
