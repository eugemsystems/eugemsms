<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book D ACA-05 §4/BR-ACA-05-010. Amending a `published` mark
 * requires the caller to have already routed the request through
 * Core's CORE-07 approval workflow and pass `approved: true` — the
 * same `overrideCeiling`/`overrideLock` pattern used elsewhere in
 * this codebase for a permission this action itself doesn't check.
 */
class MarkAmendmentRequiresApprovalException extends DomainException
{
    public static function forAssessment(int $assessmentId, int $studentId): self
    {
        return new self(
            'Amending a mark on a published assessment requires prior approval.',
            ['assessment_id' => $assessmentId, 'student_id' => $studentId],
        );
    }

    public function errorCode(): string
    {
        return 'MARK_AMENDMENT_REQUIRES_APPROVAL';
    }
}
