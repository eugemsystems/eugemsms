<?php

use Modules\Core\Domain\Actions\Settings\DefineCustomFieldAction;
use Modules\Core\Domain\Actions\Settings\ExportConfigurationProfileAction;
use Modules\Core\Domain\Actions\Settings\ImportConfigurationProfileAction;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\DataObjects\Settings\DefineCustomFieldData;
use Modules\Core\Domain\DataObjects\Settings\ExportConfigurationProfileData;
use Modules\Core\Domain\DataObjects\Settings\ImportConfigurationProfileData;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\CustomFieldDefinition;
use Modules\Core\Models\School;
use Modules\Core\Models\SettingDefinition;
use Modules\Core\Models\SettingValue;
use Modules\Core\Models\Tenant;

it('exports settings and custom fields but excludes encrypted settings (BR-CORE-04-014)', function (): void {
    $tenant = Tenant::factory()->create();
    $school = School::factory()->for($tenant)->create();

    SettingDefinition::factory()->create(['key' => 'a.plain', 'data_type' => 'string', 'lowest_scope' => 'school']);
    SettingDefinition::factory()->create(['key' => 'a.secret', 'data_type' => 'string', 'is_encrypted' => true, 'lowest_scope' => 'school']);

    app(SetSettingValueAction::class)->execute(new SetSettingValueData('a.plain', SettingScope::School, $school->id, 'hello'));
    app(SetSettingValueAction::class)->execute(new SetSettingValueData('a.secret', SettingScope::School, $school->id, 'shh'));

    app(DefineCustomFieldAction::class)->execute(new DefineCustomFieldData($school->id, 'student', 'parish', 'Parish', 'text'));

    $profile = app(ExportConfigurationProfileAction::class)->execute(new ExportConfigurationProfileData(
        tenantId: $tenant->id,
        sourceSchoolId: $school->id,
        name: 'Sunrise baseline',
    ));

    $exportedKeys = collect($profile->payload['settings'])->pluck('key');
    expect($exportedKeys)->toContain('a.plain')->not->toContain('a.secret');
    expect($profile->payload['custom_fields'])->toHaveCount(1);
});

it('imports settings and custom fields into another school, additively by default (BR-CORE-04-015/AC-CORE-04-005)', function (): void {
    $tenant = Tenant::factory()->create();
    $source = School::factory()->for($tenant)->create();
    $target = School::factory()->for($tenant)->create();

    SettingDefinition::factory()->create(['key' => 'a.plain', 'data_type' => 'string', 'lowest_scope' => 'school']);
    app(SetSettingValueAction::class)->execute(new SetSettingValueData('a.plain', SettingScope::School, $source->id, 'hello'));
    app(DefineCustomFieldAction::class)->execute(new DefineCustomFieldData($source->id, 'student', 'parish', 'Parish', 'text'));

    $profile = app(ExportConfigurationProfileAction::class)->execute(new ExportConfigurationProfileData($tenant->id, $source->id, 'Baseline'));

    $result = app(ImportConfigurationProfileAction::class)->execute(new ImportConfigurationProfileData($profile->id, $target->id));

    expect($result->settingsImported)->toBe(1)->and($result->customFieldsImported)->toBe(1);

    $imported = SettingValue::where('setting_key', 'a.plain')->where('scope_type', SettingScope::School)->where('scope_id', $target->id)->sole();
    expect($imported->value)->toBe('hello');

    expect(CustomFieldDefinition::withoutGlobalScopes()->where('school_id', $target->id)->where('key', 'parish')->exists())->toBeTrue();
});

it('does not overwrite an existing setting on the target school unless asked', function (): void {
    $tenant = Tenant::factory()->create();
    $source = School::factory()->for($tenant)->create();
    $target = School::factory()->for($tenant)->create();

    SettingDefinition::factory()->create(['key' => 'a.plain', 'data_type' => 'string', 'lowest_scope' => 'school']);
    app(SetSettingValueAction::class)->execute(new SetSettingValueData('a.plain', SettingScope::School, $source->id, 'from-source'));
    app(SetSettingValueAction::class)->execute(new SetSettingValueData('a.plain', SettingScope::School, $target->id, 'already-here'));

    $profile = app(ExportConfigurationProfileAction::class)->execute(new ExportConfigurationProfileData($tenant->id, $source->id, 'Baseline'));

    $result = app(ImportConfigurationProfileAction::class)->execute(new ImportConfigurationProfileData($profile->id, $target->id));

    expect($result->settingsSkipped)->toBe(1);

    $value = SettingValue::where('setting_key', 'a.plain')->where('scope_type', SettingScope::School)->where('scope_id', $target->id)->sole();
    expect($value->value)->toBe('already-here');
});

it('overwrites when explicitly asked', function (): void {
    $tenant = Tenant::factory()->create();
    $source = School::factory()->for($tenant)->create();
    $target = School::factory()->for($tenant)->create();

    SettingDefinition::factory()->create(['key' => 'a.plain', 'data_type' => 'string', 'lowest_scope' => 'school']);
    app(SetSettingValueAction::class)->execute(new SetSettingValueData('a.plain', SettingScope::School, $source->id, 'from-source'));
    app(SetSettingValueAction::class)->execute(new SetSettingValueData('a.plain', SettingScope::School, $target->id, 'already-here'));

    $profile = app(ExportConfigurationProfileAction::class)->execute(new ExportConfigurationProfileData($tenant->id, $source->id, 'Baseline'));

    $result = app(ImportConfigurationProfileAction::class)->execute(new ImportConfigurationProfileData($profile->id, $target->id, overwriteSettings: true));

    expect($result->settingsImported)->toBe(1);

    $value = SettingValue::where('setting_key', 'a.plain')->where('scope_type', SettingScope::School)->where('scope_id', $target->id)->sole();
    expect($value->value)->toBe('from-source');
});
