<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book E ACA-06 §6/BR-ACA-06-002. Under SBP a second brief for the
 * same subject, level and year is refused unless
 * `overrideProjectLimit` is set with a recorded reason.
 */
class DuplicateProjectBriefException extends DomainException
{
    public static function forSubjectAndLevel(int $subjectId, int $gradeLevelId, int $academicYearId): self
    {
        return new self(
            "A project brief for subject #{$subjectId}, grade level #{$gradeLevelId} and academic year #{$academicYearId} already exists this year.",
            ['subject_id' => $subjectId, 'grade_level_id' => $gradeLevelId, 'academic_year_id' => $academicYearId],
        );
    }

    public function errorCode(): string
    {
        return 'DUPLICATE_PROJECT_BRIEF';
    }
}
