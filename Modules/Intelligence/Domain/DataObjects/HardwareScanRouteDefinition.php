<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\DataObjects;

use Closure;
use Modules\People\Models\Student;

/**
 * Book J INT-04 §3/BR-INT-04-007/008. `resolver` calls its owning
 * module's own real Action directly — the same cross-module boundary
 * discipline as `Modules\Comms\Domain\DataObjects\WidgetDefinition`'s
 * `resolver` and `Modules\Intelligence\Domain\DataObjects\KpiDefinitionEntry`'s
 * `valueResolver`.
 */
final readonly class HardwareScanRouteDefinition
{
    /**
     * @param  Closure(Student, int, ?string, int, array<string, mixed>): mixed  $resolver  (student, targetId, deviceSource, recordedByUserId, context) => the owning module's own real result
     */
    public function __construct(
        public string $purpose,
        public Closure $resolver,
    ) {}
}
