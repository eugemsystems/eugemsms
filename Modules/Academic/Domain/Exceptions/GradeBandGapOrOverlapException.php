<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book D ACA-05 §5/BR-ACA-05-002/AC-ACA-05-008. Bands must touch
 * exactly, edge to edge, from 0 to 100 — see
 * `CreateGradingScaleAction`'s own docblock for the exact contiguity
 * rule this enforces.
 */
class GradeBandGapOrOverlapException extends DomainException
{
    public static function forGap(string $fromPercent, string $toPercent): self
    {
        return new self(
            "Grade bands leave a gap between {$fromPercent}% and {$toPercent}%.",
            ['from_percent' => $fromPercent, 'to_percent' => $toPercent],
        );
    }

    public static function doesNotStartAtZero(string $firstMinPercent): self
    {
        return new self(
            "Grade bands must start at 0% — the lowest band starts at {$firstMinPercent}%.",
            ['first_min_percent' => $firstMinPercent],
        );
    }

    public static function doesNotEndAt100(string $lastMaxPercent): self
    {
        return new self(
            "Grade bands must end at 100% — the highest band ends at {$lastMaxPercent}%.",
            ['last_max_percent' => $lastMaxPercent],
        );
    }

    public function errorCode(): string
    {
        return 'GRADE_BAND_GAP_OR_OVERLAP';
    }
}
