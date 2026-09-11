<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

use Closure;
use Modules\Intelligence\Domain\DataObjects\RiskIndicatorResult;

/**
 * Book J SAA-03 §4/BR-SAA-03-008. Reuses `INT-03`'s own
 * `RiskIndicatorResult` shape rather than a second explainability DTO
 * — a churn factor and a learner risk factor are the same kind of
 * fact (a severity, a plain-language sentence, a source), just at a
 * different level.
 */
final readonly class ChurnRiskIndicatorDefinition
{
    /**
     * @param  Closure(int): ?RiskIndicatorResult  $resolver  (tenantId) => a result, or null when it doesn't apply
     */
    public function __construct(
        public string $key,
        public string $moduleCode,
        public string $plainLanguageDescription,
        public float $defaultWeight,
        public Closure $resolver,
    ) {}
}
