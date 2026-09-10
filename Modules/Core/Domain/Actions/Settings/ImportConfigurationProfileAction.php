<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Settings;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Settings\ImportConfigurationProfileData;
use Modules\Core\Domain\DataObjects\Settings\ImportResult;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Events\Settings\ConfigurationProfileImported;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\ConfigurationProfile;
use Modules\Core\Models\CustomFieldDefinition;
use Modules\Core\Models\SettingValue;

/**
 * ACT-ImportConfigurationProfile (Book A CORE-04 §4). BR-CORE-04-015:
 * additive by default — an existing setting value or custom field key at
 * the target school is left untouched unless the matching
 * `overwrite*` flag is explicitly set, and every skip/overwrite decision
 * is reported back on `ImportResult` (the "logged" half of the rule).
 */
final class ImportConfigurationProfileAction extends Action
{
    public function __construct(
        private readonly SetSettingValueAction $setSettingValue,
    ) {}

    public function execute(ImportConfigurationProfileData $data): ImportResult
    {
        $profile = ConfigurationProfile::query()->findOrFail($data->profileId);
        $payload = $profile->payload;

        return $this->transaction(function () use ($payload, $data): ImportResult {
            [$settingsImported, $settingsSkipped] = $this->importSettings($payload['settings'] ?? [], $data);
            [$fieldsImported, $fieldsSkipped] = $this->importCustomFields($payload['custom_fields'] ?? [], $data);

            event(new ConfigurationProfileImported($data->profileId, $data->targetSchoolId));

            return new ImportResult($settingsImported, $settingsSkipped, $fieldsImported, $fieldsSkipped);
        });
    }

    /**
     * @param  array<int, array{key: string, value: string|null}>  $settings
     * @return array{0: int, 1: int}
     */
    private function importSettings(array $settings, ImportConfigurationProfileData $data): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($settings as $setting) {
            $exists = SettingValue::where('setting_key', $setting['key'])
                ->where('scope_type', SettingScope::School)
                ->where('scope_id', $data->targetSchoolId)
                ->exists();

            if ($exists && ! $data->overwriteSettings) {
                $skipped++;

                continue;
            }

            $this->setSettingValue->execute(new SetSettingValueData(
                key: $setting['key'],
                scopeType: SettingScope::School,
                scopeId: $data->targetSchoolId,
                value: $setting['value'],
                setByUserId: $data->actingUserId,
            ));

            $imported++;
        }

        return [$imported, $skipped];
    }

    /**
     * @param  array<int, array<string, mixed>>  $customFields
     * @return array{0: int, 1: int}
     */
    private function importCustomFields(array $customFields, ImportConfigurationProfileData $data): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($customFields as $field) {
            $existing = CustomFieldDefinition::withoutGlobalScopes()
                ->where('school_id', $data->targetSchoolId)
                ->where('entity_type', $field['entity_type'])
                ->where('key', $field['key'])
                ->first();

            if ($existing !== null && ! $data->overwriteCustomFields) {
                $skipped++;

                continue;
            }

            $attributes = [
                ...$field,
                'school_id' => $data->targetSchoolId,
                'created_by' => $data->actingUserId,
                'updated_by' => $data->actingUserId,
                'is_active' => true,
            ];

            if ($existing !== null) {
                $existing->fill($attributes);
                $existing->save();
            } else {
                CustomFieldDefinition::create($attributes);
            }

            $imported++;
        }

        return [$imported, $skipped];
    }
}
