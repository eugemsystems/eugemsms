<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Registry;

use Modules\Intelligence\Domain\DataObjects\KpiDefinitionEntry;
use Modules\Intelligence\Models\KpiDefinition;

/**
 * Book J INT-02 §2/BR-INT-02-001. Code owns the list — mirrors
 * `Modules\Comms\Domain\Registry\WidgetRegistry`'s own
 * register/all/`syncToDatabase()` shape. **Scope boundary**: this
 * pass registers a small, real starting set (see
 * `IntelligenceServiceProvider::registerKpis()`) rather than every
 * KPI a school might eventually want — the same documented boundary
 * this whole book set's other registries already draw.
 */
final class KpiRegistry
{
    /**
     * @var array<string, KpiDefinitionEntry>
     */
    private static array $kpis = [];

    public static function register(KpiDefinitionEntry $kpi): void
    {
        self::$kpis[$kpi->key] = $kpi;
    }

    public static function get(string $key): ?KpiDefinitionEntry
    {
        return self::$kpis[$key] ?? null;
    }

    /**
     * @return array<int, KpiDefinitionEntry>
     */
    public static function all(): array
    {
        return array_values(self::$kpis);
    }

    public static function clear(): void
    {
        self::$kpis = [];
    }

    public static function syncToDatabase(): void
    {
        $keys = [];

        foreach (self::$kpis as $key => $kpi) {
            $keys[] = $key;

            KpiDefinition::updateOrCreate(['key' => $key, 'school_id' => null], [
                'module_code' => $kpi->moduleCode,
                'label' => $kpi->label,
                'unit' => $kpi->unit,
                'data_source_endpoint' => '/api/v1/executive/kpis',
                'higher_is_better' => $kpi->higherIsBetter,
                'default_target_value' => $kpi->defaultTargetValue,
            ]);
        }

        KpiDefinition::query()->whereNull('school_id')->whereNotIn('key', $keys === [] ? [''] : $keys)->delete();
    }
}
