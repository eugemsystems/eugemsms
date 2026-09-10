<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

use Carbon\CarbonInterface;

/**
 * Book I COM-06 §2/BR-COM-06-001. One raw row a registered source
 * hands to `RebuildCalendarAction` — never a `CalendarEvent` itself,
 * so a source module never has to know the aggregate's own schema.
 */
final readonly class CalendarSourceRecord
{
    public function __construct(
        public int $sourceModuleId,
        public int $academicYearId,
        public ?int $termId,
        public string $title,
        public CarbonInterface $startsAt,
        public ?string $description = null,
        public ?CarbonInterface $endsAt = null,
        public bool $isAllDay = false,
        public ?string $location = null,
        public ?string $audienceScope = null,
        public ?int $audienceScopeId = null,
        public ?string $colour = null,
        public bool $isPublic = true,
    ) {}
}
