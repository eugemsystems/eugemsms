<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

use Closure;

/**
 * Book J SAA-03 §3 ⭐/BR-SAA-03-006 ⭐. `resolver` counts one owning
 * module's own real activity directly — the same cross-module
 * boundary discipline as `Modules\Intelligence\Domain\DataObjects\RiskIndicatorDefinition`'s
 * `resolver`.
 */
final readonly class ModuleAdoptionSignalDefinition
{
    /**
     * @param  Closure(int, string): int  $resolver  (schoolId, periodMonth 'Y-m') => activity count for that school and month
     */
    public function __construct(
        public string $moduleCode,
        public string $activitySignal,
        public Closure $resolver,
        public int $activeThreshold = 1,
    ) {}
}
