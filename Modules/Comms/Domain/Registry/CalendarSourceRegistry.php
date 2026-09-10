<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Registry;

use Modules\Comms\Domain\DataObjects\CalendarSourceDefinition;
use Modules\Comms\Models\CalendarSource;

/**
 * Book I COM-06 §2 ⭐/BR-COM-06-001. Code owns the list — mirrors
 * `Modules\Comms\Domain\Registry\WidgetRegistry`'s own
 * register/all/`syncToDatabase()` shape. **Scope boundary**: this
 * pass registers a small, real starting set (see
 * `CommsServiceProvider::registerCalendarSources()`) rather than
 * every dated module the spec eventually wants wired in (`ACA-07`,
 * `OPS-07`, `BRD-03`) — the aggregation engine is complete and
 * extensible, the same documented boundary this book's other
 * registries already draw.
 */
final class CalendarSourceRegistry
{
    /**
     * @var array<string, CalendarSourceDefinition>
     */
    private static array $sources = [];

    public static function register(CalendarSourceDefinition $definition): void
    {
        self::$sources[$definition->sourceType] = $definition;
    }

    public static function get(string $sourceType): ?CalendarSourceDefinition
    {
        return self::$sources[$sourceType] ?? null;
    }

    /**
     * @return array<string, CalendarSourceDefinition>
     */
    public static function all(): array
    {
        return self::$sources;
    }

    public static function clear(): void
    {
        self::$sources = [];
    }

    public static function syncToDatabase(): void
    {
        $types = [];

        foreach (self::$sources as $sourceType => $definition) {
            $types[] = $sourceType;

            CalendarSource::updateOrCreate(['source_type' => $sourceType], [
                'module_code' => $definition->moduleCode,
                'default_colour' => $definition->defaultColour,
                'default_audience_scope' => $definition->defaultAudienceScope,
                'is_active' => true,
            ]);
        }

        CalendarSource::query()->whereNotIn('source_type', $types)->delete();
    }
}
