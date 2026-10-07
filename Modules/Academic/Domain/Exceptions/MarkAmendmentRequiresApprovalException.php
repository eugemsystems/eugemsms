<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book D ACA-05 §4/BR-ACA-05-010. `AmendMarkAction` throws this for
 * every `published` assessment, unconditionally — amending one
 * requires routing through Core's real CORE-07 approval workflow via
 * `RequestMarkAmendmentAction` instead, which creates a
 * `MarkAmendmentRequest` and only applies the change from its own
 * `onApproved()` once an actual approval chain has run.
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
