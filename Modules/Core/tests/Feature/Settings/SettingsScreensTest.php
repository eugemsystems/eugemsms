<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Livewire\CustomFields\Builder as CustomFieldsBuilder;
use Modules\Core\Livewire\CustomFields\Index as CustomFieldsIndex;
use Modules\Core\Livewire\FeatureFlags\Index as FeatureFlagsIndex;
use Modules\Core\Livewire\Profiles\Index as ProfilesIndex;
use Modules\Core\Livewire\Settings\Edit as SettingsEdit;
use Modules\Core\Livewire\Settings\History as SettingsHistory;
use Modules\Core\Livewire\Settings\Index as SettingsIndex;
use Modules\Core\Models\ConfigurationProfile;
use Modules\Core\Models\CustomFieldDefinition;
use Modules\Core\Models\FeatureFlag;
use Modules\Core\Models\School;
use Modules\Core\Models\SettingDefinition;
use Modules\Core\Models\SettingValue;

function assignedSchoolForSettings(User $user): School
{
    $school = School::factory()->create();
    $user->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);

    return $school;
}

it('lists setting definitions with their resolved value for the school', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSettings($user);
    SettingDefinition::factory()->create(['key' => 'core.demo_flag', 'label' => 'Demo flag', 'data_type' => 'bool', 'default_value' => '0', 'lowest_scope' => 'school']);

    // The real app already ships dozens of registered SettingDefinition
    // rows (synced from other modules' own migrations), so the new row
    // isn't guaranteed to land on the DataTable's first page — search
    // for it, exactly as an admin would.
    Livewire::actingAs($user)
        ->test(SettingsIndex::class, ['school' => $school])
        ->set('search', 'core.demo_flag')
        ->assertSee('core.demo_flag')
        ->assertSee('Demo flag');
});

it('sets and resets a school-scoped setting value', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSettings($user);
    SettingDefinition::factory()->create(['key' => 'core.max_students', 'data_type' => 'int', 'default_value' => '30', 'lowest_scope' => 'school']);

    $component = Livewire::actingAs($user)
        ->test(SettingsEdit::class, ['school' => $school, 'key' => 'core.max_students'])
        ->assertSet('schoolScopeAllowed', true)
        ->set('value', '45')
        ->call('save')
        ->assertHasNoErrors();

    expect(SettingValue::where('setting_key', 'core.max_students')->where('scope_id', $school->id)->first()?->value)->toBe('45');

    $component->call('resetToInherited')->assertSet('hasOverride', false);

    expect(SettingValue::where('setting_key', 'core.max_students')->where('scope_id', $school->id)->exists())->toBeFalse();
});

it('refuses to edit a setting whose lowest scope is narrower than school', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSettings($user);
    SettingDefinition::factory()->create(['key' => 'core.per_user_thing', 'lowest_scope' => 'user']);

    Livewire::actingAs($user)
        ->test(SettingsEdit::class, ['school' => $school, 'key' => 'core.per_user_thing'])
        ->assertSet('schoolScopeAllowed', false);
});

it('logs a setting change and shows it in the history screen', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSettings($user);
    SettingDefinition::factory()->create(['key' => 'core.logged_setting', 'lowest_scope' => 'school']);

    Livewire::actingAs($user)
        ->test(SettingsEdit::class, ['school' => $school, 'key' => 'core.logged_setting'])
        ->set('value', 'new value')
        ->call('save');

    Livewire::actingAs($user)
        ->test(SettingsHistory::class, ['school' => $school])
        ->assertSee('core.logged_setting')
        ->assertSee('new value');
});

it('defines a custom field then deactivates it', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSettings($user);

    Livewire::actingAs($user)
        ->test(CustomFieldsBuilder::class, ['school' => $school])
        ->set('entityType', 'student')
        ->set('key', 'blood_type')
        ->set('label', 'Blood type')
        ->set('dataType', 'text')
        ->call('create')
        ->assertHasNoErrors();

    $definition = CustomFieldDefinition::where('school_id', $school->id)->where('key', 'blood_type')->sole();
    expect($definition->is_active)->toBeTrue();

    Livewire::actingAs($user)
        ->test(CustomFieldsIndex::class, ['school' => $school])
        ->assertSee('blood_type')
        ->call('deactivate', $definition->id)
        ->assertHasNoErrors();

    expect($definition->fresh()->is_active)->toBeFalse();
});

it('deletes an unused deactivated custom field', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSettings($user);
    $definition = CustomFieldDefinition::factory()->for($school)->create(['is_active' => false]);

    Livewire::actingAs($user)
        ->test(CustomFieldsIndex::class, ['school' => $school])
        ->call('delete', $definition->id)
        ->assertHasNoErrors();

    expect(CustomFieldDefinition::withoutGlobalScopes()->find($definition->id))->toBeNull();
});

it('toggles a feature flag globally and adds a scoped override', function (): void {
    $user = User::factory()->create();
    $flag = FeatureFlag::factory()->create(['key' => 'new-dashboard', 'is_globally_enabled' => false]);

    $component = Livewire::actingAs($user)
        ->test(FeatureFlagsIndex::class)
        ->call('toggleGlobal', 'new-dashboard', true)
        ->assertHasNoErrors();

    expect($flag->fresh()->is_globally_enabled)->toBeTrue();

    $component->call('openOverrideModal', $flag->id)
        ->set('overrideScopeType', 'school')
        ->set('overrideScopeId', '1')
        ->set('overrideEnabled', false)
        ->call('saveOverride')
        ->assertHasNoErrors();

    expect($flag->overrides()->where('scope_type', 'school')->where('scope_id', 1)->where('is_enabled', false)->exists())->toBeTrue();
});

it('exports a school as a configuration profile and imports it additively into another school', function (): void {
    $user = User::factory()->create();
    $sourceSchool = assignedSchoolForSettings($user);
    $targetSchool = School::factory()->create(['tenant_id' => $sourceSchool->tenant_id]);
    $user->schools()->attach($targetSchool, ['is_primary' => false, 'status' => 'active']);

    SettingDefinition::factory()->create(['key' => 'core.exportable', 'lowest_scope' => 'school']);
    SettingValue::create(['setting_key' => 'core.exportable', 'scope_type' => 'school', 'scope_id' => $sourceSchool->id, 'value' => 'shared-value']);

    Livewire::actingAs($user)
        ->test(ProfilesIndex::class, ['school' => $sourceSchool])
        ->set('exportName', 'Standard setup')
        ->call('export')
        ->assertHasNoErrors();

    $profile = ConfigurationProfile::where('tenant_id', $sourceSchool->tenant_id)->where('name', 'Standard setup')->sole();

    Livewire::actingAs($user)
        ->test(ProfilesIndex::class, ['school' => $targetSchool])
        ->call('openImportModal', $profile->id)
        ->call('import')
        ->assertHasNoErrors();

    expect(SettingValue::where('setting_key', 'core.exportable')->where('scope_id', $targetSchool->id)->first()?->value)->toBe('shared-value');
});
