<?php

declare(strict_types=1);

namespace Modules\Security\Domain\DataObjects;

/**
 * `category` is one of `sick_bay|boarder|day_scholar|staff|visitor|
 * contractor` — `sick_bay` is its own category (BR-OPS-06-009 lists
 * it as a distinct roster line, not a flag on an existing boarder/day
 * -scholar entry, and a student currently in sick bay is excluded
 * from those two categories to avoid counting them twice).
 * `needsAssistance` covers both sick bay occupants and any learner
 * with `requires_evacuation_assistance` recorded — both appear first
 * on every list per the same rule.
 */
final readonly class MusterRosterEntry
{
    public function __construct(
        public string $category,
        public string $personType,
        public int $personId,
        public bool $needsAssistance = false,
    ) {}
}
