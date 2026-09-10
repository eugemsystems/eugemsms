<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

/**
 * Book E ACA-06 §4 ⭐ — the shape `ContinuousAssessmentProvider` hands
 * back to `ACA-05`. `instrumentCode` is `'SBP'` or `'CALA'` and drives
 * report card labelling with no branching in the template itself.
 */
final readonly class ContinuousAssessmentOutcome
{
    public function __construct(
        public string $instrumentCode,
        public ?float $percent,
        public ?string $grade,
        public string $status,
        public ?string $projectTitle,
        public bool $isVerified,
    ) {}
}
