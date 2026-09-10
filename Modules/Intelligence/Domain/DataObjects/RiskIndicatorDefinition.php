<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\DataObjects;

use Closure;

/**
 * Book J INT-03 §2/BR-INT-03-003. `resolver` calls its owning module's
 * own real domain data directly — the same cross-module boundary
 * discipline as `Modules\Comms\Domain\DataObjects\WidgetDefinition`'s
 * `resolver` and `Modules\Intelligence\Domain\DataObjects\KpiDefinitionEntry`'s
 * `valueResolver`.
 */
final readonly class RiskIndicatorDefinition
{
    /**
     * @param  string  $appliesTo  'learner'|'staff'
     * @param  Closure(int, int, int): ?RiskIndicatorResult  $resolver  (schoolId, subjectId — studentId or staffId, termId) => a result, or null when it doesn't apply
     */
    public function __construct(
        public string $key,
        public string $moduleCode,
        public string $appliesTo,
        public string $plainLanguageDescription,
        public float $defaultWeight,
        public Closure $resolver,
    ) {}
}
