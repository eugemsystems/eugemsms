<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book D ACA-02 §5/BR-ACA-02-005 (AC-ACA-02-007). An add or drop after
 * `academic.subject_change_cutoff_week` changes an already-issued
 * invoice and must route through `CORE-07` approval. Raising the
 * exception here — rather than creating the `approval_requests` row
 * directly — defers the actual chain wiring: that needs a configured
 * `approval_chains` row for "late subject change" per school, which
 * this pass does not seed. `EnrolSubjectAction`/`DropSubjectAction`
 * document this as an explicit scope boundary, the same way `FIN-06`'s
 * `CalculateRealisedFxAction` deferred settlement-journal posting to
 * `FIN-04`.
 */
class SubjectChangeRequiresApprovalException extends DomainException
{
    public static function pastCutoff(int $cutoffWeek): self
    {
        return new self(
            "This change is past week {$cutoffWeek} of the term and requires approval before it can take effect.",
            ['cutoff_week' => $cutoffWeek],
        );
    }

    public function errorCode(): string
    {
        return 'SUBJECT_CHANGE_REQUIRES_APPROVAL';
    }
}
