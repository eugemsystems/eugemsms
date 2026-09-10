<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Settings;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Settings\ExportConfigurationProfileData;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\ConfigurationProfile;
use Modules\Core\Models\CustomFieldDefinition;
use Modules\Core\Models\SettingDefinition;
use Modules\Core\Models\SettingValue;

/**
 * ACT-ExportConfigurationProfile (Book A CORE-04 §4). BR-CORE-04-014: a
 * configuration profile carries structure and configuration only —
 * `payload` here is exactly `custom_field_definitions` and school-scoped
 * `setting_values` (encrypted ones excluded outright, not merely masked
 * — BR-CORE-04-005's export-masking guarantee is strongest as "never
 * leaves the source school at all"). Roles and document templates belong
 * to CORE-05/CORE-06 (not built yet); `payload['roles']`/`['templates']`
 * are reserved, empty keys until those modules populate them.
 */
final class ExportConfigurationProfileAction extends Action
{
    private const string VERSION = '1.0';

    public function execute(ExportConfigurationProfileData $data): ConfigurationProfile
    {
        $encryptedKeys = SettingDefinition::where('is_encrypted', true)->pluck('key');

        $settingValues = SettingValue::where('scope_type', SettingScope::School)
            ->where('scope_id', $data->sourceSchoolId)
            ->whereNotIn('setting_key', $encryptedKeys)
            ->get(['setting_key', 'value'])
            ->map(fn (SettingValue $value): array => ['key' => $value->setting_key, 'value' => $value->value])
            ->values()
            ->all();

        $customFields = CustomFieldDefinition::withoutGlobalScopes()
            ->where('school_id', $data->sourceSchoolId)
            ->where('is_active', true)
            ->get()
            ->map(fn (CustomFieldDefinition $field): array => $field->only([
                'entity_type', 'key', 'label', 'description', 'data_type', 'options',
                'validation_rules', 'is_required', 'is_searchable', 'is_exposed_in_api',
                'is_printable', 'visible_to_roles', 'group_label', 'sort_order',
            ]))
            ->values()
            ->all();

        return $this->transaction(fn (): ConfigurationProfile => ConfigurationProfile::create([
            'tenant_id' => $data->tenantId,
            'name' => $data->name,
            'description' => $data->description,
            'source_school_id' => $data->sourceSchoolId,
            'payload' => [
                'settings' => $settingValues,
                'custom_fields' => $customFields,
                'roles' => [],
                'templates' => [],
            ],
            'version' => self::VERSION,
            'created_by' => $data->actingUserId,
            'created_at' => now(),
        ]));
    }
}
