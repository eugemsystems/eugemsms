<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

use Closure;
use Illuminate\Support\Collection;

/**
 * Book I COM-06 §2 ⭐/BR-COM-06-001. `syncer` calls its owning
 * module's own domain layer directly — it never lets `RebuildCalendarAction`
 * query that module's tables itself (the same cross-module boundary
 * discipline as `Modules\Comms\Domain\DataObjects\WidgetDefinition`'s
 * `resolver`).
 */
final readonly class CalendarSourceDefinition
{
    /**
     * @param  Closure(int): Collection<int, CalendarSourceRecord>  $syncer  (schoolId) => every current record this source owns, right now
     */
    public function __construct(
        public string $moduleCode,
        public string $sourceType,
        public string $defaultAudienceScope,
        public Closure $syncer,
        public ?string $defaultColour = null,
    ) {}
}
