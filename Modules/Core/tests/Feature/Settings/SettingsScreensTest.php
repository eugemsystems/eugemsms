<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Livewire\CustomFields\Builder as CustomFieldsBuilder;
use Modules\Core\Livewire\CustomFields\Index as CustomFieldsIndex;
use Modules\Core\Livewire\FeatureFlags\Index as FeatureFlagsIndex;
use Modules\Core\Livewire\Profiles\Index as ProfilesIndex;
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

it('lists setting definitions with their resolved value for the school, grouped under their module tab', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSettings($user);
    $definition = SettingDefinition::factory()->create(['key' => 'core.demo_flag', 'label' => 'Demo flag', 'data_type' => 'bool', 'default_value' => '0', 'lowest_scope' => 'school']);

    // The real app already ships dozens of registered SettingDefinition
    // rows (synced from other modules' own migrations) across several
    // module tabs — the new row only appears once its own module tab is
    // active, exactly as an admin would need to click to it.
    Livewire::actingAs($user)
        ->test(SettingsIndex::class, ['school' => $school])
        ->set('activeModule', $definition->module_code)
        ->assertSee('core.demo_flag')
        ->assertSee('Demo flag');
});

it('shows only the active module tab\'s settings, and switches tabs without mixing modules', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSettings($user);
    SettingDefinition::factory()->create(['key' => 'moda.setting_one', 'module_code' => 'MODA', 'label' => 'Module A Setting']);
    SettingDefinition::factory()->create(['key' => 'modb.setting_one', 'module_code' => 'MODB', 'label' => 'Module B Setting']);

    $component = Livewire::actingAs($user)
        ->test(SettingsIndex::class, ['school' => $school])
        ->set('activeModule', 'MODA')
        ->assertSee('Module A Setting')
        ->assertDontSee('Module B Setting');

    $component->set('activeModule', 'MODB')
        ->assertSee('Module B Setting')
        ->assertDontSee('Module A Setting');
});

it('sets and resets a school-scoped setting value inline via saveField/resetToInherited', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSettings($user);
    $definition = SettingDefinition::factory()->create(['key' => 'core.max_students', 'data_type' => 'int', 'default_value' => '30', 'lowest_scope' => 'school']);

    $component = Livewire::actingAs($user)
        ->test(SettingsIndex::class, ['school' => $school])
        ->set('activeModule', $definition->module_code)
        ->set("values.{$definition->id}", '45')
        ->call('saveField', $definition->id)
        ->assertHasNoErrors();

    expect(SettingValue::where('setting_key', 'core.max_students')->where('scope_id', $school->id)->first()?->value)->toBe('45');

    $component->call('resetToInherited', $definition->id);

    expect(SettingValue::where('setting_key', 'core.max_students')->where('scope_id', $school->id)->exists())->toBeFalse();
});

it('does not persist a deferred (text/select/etc.) setting until its explicit save is called', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSettings($user);
    $definition = SettingDefinition::factory()->create(['key' => 'core.pending_text', 'data_type' => 'string', 'lowest_scope' => 'school']);

    Livewire::actingAs($user)
        ->test(SettingsIndex::class, ['school' => $school])
        ->set('activeModule', $definition->module_code)
        ->set("values.{$definition->id}", 'not saved yet');

    expect(SettingValue::where('setting_key', 'core.pending_text')->exists())->toBeFalse();
});

it('saves a bool setting immediately when its switch is toggled, with no separate save call', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSettings($user);
    $definition = SettingDefinition::factory()->create(['key' => 'core.demo_toggle', 'data_type' => 'bool', 'default_value' => '0', 'lowest_scope' => 'school']);

    Livewire::actingAs($user)
        ->test(SettingsIndex::class, ['school' => $school])
        ->set('activeModule', $definition->module_code)
        ->call('saveBool', $definition->id)
        ->assertHasNoErrors();

    expect(SettingValue::where('setting_key', 'core.demo_toggle')->where('scope_id', $school->id)->first()?->value)->toBe('1');
});

it('refuses to edit a setting whose lowest scope is narrower than school', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSettings($user);
    $definition = SettingDefinition::factory()->create(['key' => 'core.per_user_thing', 'lowest_scope' => 'user']);

    Livewire::actingAs($user)
        ->test(SettingsIndex::class, ['school' => $school])
        ->set('activeModule', $definition->module_code)
        ->set("values.{$definition->id}", 'nope')
        ->call('saveField', $definition->id);

    expect(SettingValue::where('setting_key', 'core.per_user_thing')->exists())->toBeFalse();
});

it('logs a setting change and shows it in the history screen', function (): void {
    $user = User::factory()->create();
    $school = assignedSchoolForSettings($user);
    $definition = SettingDefinition::factory()->create(['key' => 'core.logged_setting', 'lowest_scope' => 'school']);

    Livewire::actingAs($user)
        ->test(SettingsIndex::class, ['school' => $school])
        ->set('activeModule', $definition->module_code)
        ->set("values.{$definition->id}", 'new value')
        ->call('saveField', $definition->id);

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
