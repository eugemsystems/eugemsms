<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Registry;

use Modules\Comms\Domain\DataObjects\WidgetDefinition;
use Modules\Comms\Models\DashboardWidget;

/**
 * Book I COM-03 §2 ⭐/BR-COM-03-001. Code owns the list — mirrors
 * `Modules\Core\Domain\Registry\SettingDefinitionRegistry`'s own
 * register/all/`syncToDatabase()` shape. **Scope boundary**: this
 * pass registers a small, real starting set per persona (see
 * `CommsServiceProvider::registerDashboardWidgets()`) rather than
 * every widget every owning module could eventually expose — the
 * registry architecture is complete and extensible, the same
 * documented boundary this book's other registries already draw.
 */
final class WidgetRegistry
{
    /**
     * @var array<string, WidgetDefinition>
     */
    private static array $widgets = [];

    public static function register(WidgetDefinition $definition): void
    {
        self::$widgets[$definition->key] = $definition;
    }

    public static function get(string $key): ?WidgetDefinition
    {
        return self::$widgets[$key] ?? null;
    }

    /**
     * @return array<int, WidgetDefinition>
     */
    public static function forPersona(string $persona): array
    {
        return array_values(array_filter(self::$widgets, fn (WidgetDefinition $w): bool => $w->persona === $persona));
    }

    /**
     * @return array<string, WidgetDefinition>
     */
    public static function all(): array
    {
        return self::$widgets;
    }

    public static function clear(): void
    {
        self::$widgets = [];
    }

    public static function syncToDatabase(): void
    {
        $keys = [];

        foreach (self::$widgets as $key => $definition) {
            $keys[] = $key;

            DashboardWidget::updateOrCreate(['key' => $key], [
                'module_code' => $definition->moduleCode,
                'persona' => $definition->persona,
                'title' => $definition->title,
                'data_endpoint' => $definition->dataEndpoint,
                'min_grade_ordinal' => $definition->minGradeOrdinal,
                'requires_module' => $definition->requiresModule,
                'default_enabled' => $definition->defaultEnabled,
                'default_sort_order' => $definition->defaultSortOrder,
            ]);
        }

        DashboardWidget::query()->whereNotIn('key', $keys)->delete();
    }
}
