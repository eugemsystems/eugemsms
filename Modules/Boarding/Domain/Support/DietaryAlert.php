<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Support;

/**
 * Book F BRD-04 §4/BR-BRD-04-009/011 ⭐ — the serving terminal's own
 * output. "This is a safeguarding control, not a convenience": every
 * field here is meant to render in large type with the learner's
 * photograph, at every meal, not only the first.
 */
final readonly class DietaryAlert
{
    public function __construct(
        public string $requirementType,
        public string $severity,
        public string $description,
        public bool $requiresEpipen,
        public bool $isVerified,
    ) {}
}
