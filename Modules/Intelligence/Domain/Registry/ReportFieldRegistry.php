<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Registry;

use Modules\Intelligence\Domain\DataObjects\ReportEntityDefinition;
use Modules\Intelligence\Domain\DataObjects\ReportFieldDefinition;
use Modules\Intelligence\Models\ReportEntity;
use Modules\Intelligence\Models\ReportField;

/**
 * Book J INT-01 §2/§3 ⭐/BR-INT-01-001. Code owns the list — mirrors
 * `Modules\Comms\Domain\Registry\WidgetRegistry`'s own
 * register/all/`syncToDatabase()` shape. **Scope boundary**: this
 * pass registers a small, real starting set (see
 * `IntelligenceServiceProvider::registerReportFields()`) rather than
 * every field every module could eventually expose — the same
 * documented boundary this whole specification's other registries
 * already draw (`CalendarSourceRegistry`, `AutomationEntityRegistry`).
 */
final class ReportFieldRegistry
{
    /**
     * @var array<string, ReportEntityDefinition>
     */
    private static array $entities = [];

    /**
     * @var array<string, array<string, ReportFieldDefinition>>
     */
    private static array $fields = [];

    public static function registerEntity(ReportEntityDefinition $entity): void
    {
        self::$entities[$entity->entityKey] = $entity;
    }

    public static function registerField(ReportFieldDefinition $field): void
    {
        self::$fields[$field->entityKey][$field->fieldKey] = $field;
    }

    public static function getEntity(string $entityKey): ?ReportEntityDefinition
    {
        return self::$entities[$entityKey] ?? null;
    }

    public static function getField(string $entityKey, string $fieldKey): ?ReportFieldDefinition
    {
        return self::$fields[$entityKey][$fieldKey] ?? null;
    }

    /**
     * @return array<int, ReportFieldDefinition>
     */
    public static function fieldsForEntity(string $entityKey): array
    {
        return array_values(self::$fields[$entityKey] ?? []);
    }

    /**
     * @return array<string, ReportEntityDefinition>
     */
    public static function allEntities(): array
    {
        return self::$entities;
    }

    public static function clear(): void
    {
        self::$entities = [];
        self::$fields = [];
    }

    public static function syncToDatabase(): void
    {
        $entityKeys = [];

        foreach (self::$entities as $entityKey => $entity) {
            $entityKeys[] = $entityKey;

            ReportEntity::updateOrCreate(['entity_key' => $entityKey], [
                'module_code' => $entity->moduleCode,
                'base_model_class' => $entity->baseModelClass,
                'default_school_scoped' => $entity->defaultSchoolScoped,
                'allowed_join_entity_keys' => $entity->allowedJoinEntityKeys === [] ? null : $entity->allowedJoinEntityKeys,
            ]);
        }

        ReportEntity::query()->whereNotIn('entity_key', $entityKeys === [] ? [''] : $entityKeys)->delete();

        $fieldIds = [];

        foreach (self::$fields as $entityFields) {
            foreach ($entityFields as $field) {
                $row = ReportField::updateOrCreate(
                    ['module_code' => $field->moduleCode, 'entity_key' => $field->entityKey, 'field_key' => $field->fieldKey],
                    [
                        'label' => $field->label,
                        'data_type' => $field->dataType,
                        'is_filterable' => $field->isFilterable,
                        'is_groupable' => $field->isGroupable,
                        'is_aggregatable' => $field->isAggregatable,
                        'required_permission' => $field->requiredPermission,
                        'is_sensitive' => $field->isSensitive,
                        'enum_options' => $field->enumOptions,
                    ],
                );
                $fieldIds[] = $row->id;
            }
        }

        ReportField::query()->whereNotIn('id', $fieldIds === [] ? [0] : $fieldIds)->delete();
    }
}
