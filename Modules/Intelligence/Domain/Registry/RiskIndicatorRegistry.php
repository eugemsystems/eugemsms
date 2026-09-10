<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Registry;

use Modules\Intelligence\Domain\DataObjects\RiskIndicatorDefinition;
use Modules\Intelligence\Models\RiskIndicator;

/**
 * Book J INT-03 §2/BR-INT-03-003. Code owns the list — mirrors
 * `Modules\Intelligence\Domain\Registry\KpiRegistry`'s own
 * register/get/all/`syncToDatabase()` shape. **Scope boundary**: this
 * pass registers a small, real starting set of learner indicators
 * (see `IntelligenceServiceProvider::registerRiskIndicators()`)
 * mirroring the specification's own worked example, plus one staff
 * indicator — not every conceivable signal a school might eventually
 * want, the same documented boundary this book set's other registries
 * already draw.
 */
final class RiskIndicatorRegistry
{
    /**
     * @var array<string, RiskIndicatorDefinition>
     */
    private static array $indicators = [];

    public static function register(RiskIndicatorDefinition $indicator): void
    {
        self::$indicators[$indicator->key] = $indicator;
    }

    public static function get(string $key): ?RiskIndicatorDefinition
    {
        return self::$indicators[$key] ?? null;
    }

    /**
     * @return array<int, RiskIndicatorDefinition>
     */
    public static function forAppliesTo(string $appliesTo): array
    {
        return array_values(array_filter(self::$indicators, fn (RiskIndicatorDefinition $i): bool => $i->appliesTo === $appliesTo));
    }

    /**
     * @return array<int, RiskIndicatorDefinition>
     */
    public static function all(): array
    {
        return array_values(self::$indicators);
    }

    public static function clear(): void
    {
        self::$indicators = [];
    }

    public static function syncToDatabase(): void
    {
        $keys = [];

        foreach (self::$indicators as $key => $indicator) {
            $keys[] = $key;

            RiskIndicator::updateOrCreate(['key' => $key], [
                'module_code' => $indicator->moduleCode,
                'applies_to' => $indicator->appliesTo,
                'plain_language_description' => $indicator->plainLanguageDescription,
                'default_weight' => $indicator->defaultWeight,
            ]);
        }

        RiskIndicator::query()->whereNotIn('key', $keys === [] ? [''] : $keys)->delete();
    }
}
