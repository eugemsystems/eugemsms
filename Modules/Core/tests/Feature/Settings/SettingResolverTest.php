<?php

use Illuminate\Support\Facades\Crypt;
use Modules\Core\Domain\Exceptions\UnregisteredSettingException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\SettingDefinition;
use Modules\Core\Models\SettingValue;

it('throws for a key no module has registered (BR-CORE-04-001)', function (): void {
    app(SettingResolver::class)->get('nobody.registered.this', new ScopeChain);
})->throws(UnregisteredSettingException::class);

it('falls back to the definition default when no value is set at any scope (BR-CORE-04-002)', function (): void {
    SettingDefinition::factory()->create(['key' => 'academic.terms_per_year', 'data_type' => 'int', 'default_value' => '3', 'lowest_scope' => 'school']);

    $value = app(SettingResolver::class)->get('academic.terms_per_year', new ScopeChain(schoolId: 1, tenantId: 1));

    expect($value)->toBe(3);
});

it('resolves the most specific defined value first (AC-CORE-04-001)', function (): void {
    SettingDefinition::factory()->create(['key' => 'academic.terms_per_year', 'data_type' => 'int', 'default_value' => '3', 'lowest_scope' => 'school']);

    SettingValue::create(['setting_key' => 'academic.terms_per_year', 'scope_type' => SettingScope::Tenant, 'scope_id' => 1, 'value' => '3']);
    SettingValue::create(['setting_key' => 'academic.terms_per_year', 'scope_type' => SettingScope::School, 'scope_id' => 2, 'value' => '2']);

    $schoolA = app(SettingResolver::class)->get('academic.terms_per_year', new ScopeChain(schoolId: 1, tenantId: 1));
    $schoolB = app(SettingResolver::class)->get('academic.terms_per_year', new ScopeChain(schoolId: 2, tenantId: 1));

    expect($schoolA)->toBe(3)->and($schoolB)->toBe(2);
});

it('casts every data type correctly', function (): void {
    SettingDefinition::factory()->create(['key' => 'a.bool', 'data_type' => 'bool', 'default_value' => '1']);
    SettingDefinition::factory()->create(['key' => 'a.float', 'data_type' => 'float', 'default_value' => '1.5']);
    SettingDefinition::factory()->create(['key' => 'a.json', 'data_type' => 'json', 'default_value' => '{"a":1}']);

    $resolver = app(SettingResolver::class);
    $chain = new ScopeChain(schoolId: 1);

    expect($resolver->get('a.bool', $chain))->toBeTrue()
        ->and($resolver->get('a.float', $chain))->toBe(1.5)
        ->and($resolver->get('a.json', $chain))->toBe(['a' => 1]);
});

it('decrypts an encrypted setting transparently', function (): void {
    $definition = SettingDefinition::factory()->create(['key' => 'gateway.secret', 'data_type' => 'string', 'is_encrypted' => true]);

    SettingValue::create([
        'setting_key' => 'gateway.secret',
        'scope_type' => SettingScope::School,
        'scope_id' => 1,
        'value' => Crypt::encryptString('super-secret'),
    ]);

    $value = app(SettingResolver::class)->get('gateway.secret', new ScopeChain(schoolId: 1));

    expect($value)->toBe('super-secret');
});

it('caches a resolved value and does not re-query the database on a second call', function (): void {
    SettingDefinition::factory()->create(['key' => 'cached.key', 'data_type' => 'string', 'default_value' => 'default']);
    SettingValue::create(['setting_key' => 'cached.key', 'scope_type' => SettingScope::School, 'scope_id' => 1, 'value' => 'first']);

    $resolver = app(SettingResolver::class);
    $chain = new ScopeChain(schoolId: 1);

    expect($resolver->get('cached.key', $chain))->toBe('first');

    SettingValue::where('setting_key', 'cached.key')->delete();

    // Still 'first': the cache entry, not a fresh DB read, answers this.
    expect($resolver->get('cached.key', $chain))->toBe('first');

    SettingResolver::forget('cached.key', SettingScope::School, 1);

    expect($resolver->get('cached.key', $chain))->toBe('default');
});
