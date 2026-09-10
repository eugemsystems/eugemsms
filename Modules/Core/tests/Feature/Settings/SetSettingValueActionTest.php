<?php

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\Contracts\Settings\TenantTierProvider;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Exceptions\InvalidSettingScopeException;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Exceptions\UnregisteredSettingException;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\SettingChangeLog;
use Modules\Core\Models\SettingDefinition;
use Modules\Core\Models\Tenant;

it('sets a value and writes an append-only change log entry (BR-CORE-04-006)', function (): void {
    SettingDefinition::factory()->create(['key' => 'academic.terms_per_year', 'data_type' => 'int', 'lowest_scope' => 'school']);
    $user = User::factory()->create();

    app(SetSettingValueAction::class)->execute(new SetSettingValueData(
        key: 'academic.terms_per_year',
        scopeType: SettingScope::School,
        scopeId: 1,
        value: 3,
        setByUserId: $user->id,
    ));

    $log = SettingChangeLog::where('setting_key', 'academic.terms_per_year')->sole();
    expect($log->new_value)->toBe('3')->and($log->changed_by)->toBe($user->id);
});

it('throws for an unregistered key', function (): void {
    app(SetSettingValueAction::class)->execute(new SetSettingValueData('nobody.registered', SettingScope::School, 1, 'x'));
})->throws(UnregisteredSettingException::class);

it('refuses to set a value more general than lowest_scope (AC-CORE-04-002)', function (): void {
    SettingDefinition::factory()->create(['key' => 'a.setting', 'data_type' => 'string', 'lowest_scope' => 'school']);

    app(SetSettingValueAction::class)->execute(new SetSettingValueData('a.setting', SettingScope::Tenant, 1, 'x'));
})->throws(InvalidSettingScopeException::class);

it('allows setting a value exactly at lowest_scope', function (): void {
    SettingDefinition::factory()->create(['key' => 'a.setting', 'data_type' => 'string', 'lowest_scope' => 'school']);

    $value = app(SetSettingValueAction::class)->execute(new SetSettingValueData('a.setting', SettingScope::School, 1, 'x'));

    expect($value->exists)->toBeTrue();
});

it('allows setting a value more specific than lowest_scope', function (): void {
    SettingDefinition::factory()->create(['key' => 'a.setting', 'data_type' => 'string', 'lowest_scope' => 'school']);

    $value = app(SetSettingValueAction::class)->execute(new SetSettingValueData('a.setting', SettingScope::User, 1, 'x'));

    expect($value->exists)->toBeTrue();
});

it('encrypts an is_encrypted setting at rest (AC-CORE-04-004)', function (): void {
    SettingDefinition::factory()->create(['key' => 'gateway.secret', 'data_type' => 'string', 'is_encrypted' => true, 'lowest_scope' => 'school']);

    $value = app(SetSettingValueAction::class)->execute(new SetSettingValueData('gateway.secret', SettingScope::School, 1, 'super-secret'));

    expect($value->value)->not->toBe('super-secret')
        ->and(Crypt::decryptString($value->value))->toBe('super-secret');
});

it('redacts an is_encrypted setting in the change log', function (): void {
    SettingDefinition::factory()->create(['key' => 'gateway.secret', 'data_type' => 'string', 'is_encrypted' => true, 'lowest_scope' => 'school']);

    app(SetSettingValueAction::class)->execute(new SetSettingValueData('gateway.secret', SettingScope::School, 1, 'super-secret'));

    $log = SettingChangeLog::where('setting_key', 'gateway.secret')->sole();
    expect($log->new_value)->toBe('[redacted]');
});

it('rejects a value failing the data type validation', function (): void {
    SettingDefinition::factory()->create(['key' => 'a.number', 'data_type' => 'int', 'lowest_scope' => 'school']);

    app(SetSettingValueAction::class)->execute(new SetSettingValueData('a.number', SettingScope::School, 1, 'not-a-number'));
})->throws(ValidationException::class);

it('refuses to change a tier-locked setting once the tenant is at or above that tier (BR-CORE-04-007)', function (): void {
    $tenant = Tenant::factory()->create();
    SettingDefinition::factory()->create(['key' => 'academic.require_dual_approval_for_reopen', 'data_type' => 'bool', 'is_locked_on_tier' => 'enterprise', 'lowest_scope' => 'tenant']);

    $tierProvider = Mockery::mock(TenantTierProvider::class);
    $tierProvider->shouldReceive('tierFor')->andReturn('enterprise');
    $tierProvider->shouldReceive('isAtLeast')->with('enterprise', 'enterprise')->andReturn(true);
    app()->instance(TenantTierProvider::class, $tierProvider);

    app(SetSettingValueAction::class)->execute(new SetSettingValueData(
        'academic.require_dual_approval_for_reopen', SettingScope::Tenant, $tenant->id, false,
    ));
})->throws(InvalidStateTransitionException::class);
