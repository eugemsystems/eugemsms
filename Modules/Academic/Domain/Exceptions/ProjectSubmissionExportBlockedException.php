<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book E ACA-06 §6/BR-ACA-06-018. Export is refused outright while any
 * candidate in the set still has an `error`-severity validation issue
 * — the validation report must be clean first, mirroring Book H3
 * CMP-01's own `ZimsecExportBlockedException`.
 */
final class ProjectSubmissionExportBlockedException extends DomainException
{
    public static function hasErrors(int $instrumentId, int $academicYearId, int $errorCount): self
    {
        return new self(
            "This submission cannot be exported: {$errorCount} candidate(s) still have validation errors.",
            ['instrument_id' => $instrumentId, 'academic_year_id' => $academicYearId, 'error_count' => $errorCount],
        );
    }

    public function errorCode(): string
    {
        return 'PROJECT_SUBMISSION_EXPORT_BLOCKED';
    }
}
