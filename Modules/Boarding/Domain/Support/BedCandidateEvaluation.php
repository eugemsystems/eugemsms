<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Support;

use Modules\Boarding\Models\HostelBed;

/**
 * Book F BRD-01 §3 — one candidate bed's evaluation result.
 * `isHardValid` false means the bed is unusable for this learner no
 * matter how good its soft score; `softViolations` names every soft
 * constraint this candidate would break, for the draft-review screen
 * (BR-BRD-01-007/AC-BRD-01-004).
 */
final readonly class BedCandidateEvaluation
{
    /**
     * @param  array<int, string>  $softViolations
     */
    public function __construct(
        public HostelBed $bed,
        public bool $isHardValid,
        public ?string $hardBlockReason,
        public array $softViolations,
        public float $softScore,
    ) {}
}
