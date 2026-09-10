<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\DataObjects;

use Closure;

/**
 * Book J INT-02 §2/BR-INT-02-001. `valueResolver` calls its owning
 * module's own real domain layer directly — the same cross-module
 * boundary discipline as `Modules\Comms\Domain\DataObjects\WidgetDefinition`'s
 * own `resolver`.
 */
final readonly class KpiDefinitionEntry
{
    /**
     * @param  Closure(int): float  $valueResolver
     */
    public function __construct(
        public string $key,
        public string $moduleCode,
        public string $label,
        public string $unit,
        public Closure $valueResolver,
        public bool $higherIsBetter = true,
        public ?float $defaultTargetValue = null,
    ) {}
}
