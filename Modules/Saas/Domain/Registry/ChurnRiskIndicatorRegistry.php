<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Registry;

use Modules\Saas\Domain\DataObjects\ChurnRiskIndicatorDefinition;

/**
 * Book J SAA-03 §4/BR-SAA-03-008. Code owns the list — mirrors
 * `Modules\Intelligence\Domain\Registry\RiskIndicatorRegistry`'s own
 * shape at the tenant level instead of the learner level.
 */
final class ChurnRiskIndicatorRegistry
{
    /**
     * @var array<string, ChurnRiskIndicatorDefinition>
     */
    private static array $indicators = [];

    public static function register(ChurnRiskIndicatorDefinition $indicator): void
    {
        self::$indicators[$indicator->key] = $indicator;
    }

    public static function get(string $key): ?ChurnRiskIndicatorDefinition
    {
        return self::$indicators[$key] ?? null;
    }

    /**
     * @return array<int, ChurnRiskIndicatorDefinition>
     */
    public static function all(): array
    {
        return array_values(self::$indicators);
    }

    public static function clear(): void
    {
        self::$indicators = [];
    }
}
