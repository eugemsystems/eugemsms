<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book E ACA-07 §4/BR-ACA-07-004/AC-ACA-07-011. Paper component
 * weights within a subject/session/level must total 100%.
 */
class PaperComponentWeightMismatchException extends DomainException
{
    public static function forTotal(int $subjectId, float $total): self
    {
        return new self(
            "Exam paper component weights for subject #{$subjectId} total {$total}%, not 100%.",
            ['subject_id' => $subjectId, 'total_weight_percent' => $total],
        );
    }

    public function errorCode(): string
    {
        return 'PAPER_COMPONENT_WEIGHT_MISMATCH';
    }
}
