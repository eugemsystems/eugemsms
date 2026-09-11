<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book K ACA-08 §2/BR-ACA-08-001. A course space is a 1:1 mirror of a
 * teaching group for a term — there is no separate LMS-only class
 * list, so a second course space for the same (term, teaching group)
 * is refused rather than silently creating a duplicate.
 */
class DuplicateCourseSpaceException extends DomainException
{
    public static function forTeachingGroup(int $teachingGroupId, int $termId): self
    {
        return new self(
            "A course space for teaching group #{$teachingGroupId} and term #{$termId} already exists.",
            ['teaching_group_id' => $teachingGroupId, 'term_id' => $termId],
        );
    }

    public function errorCode(): string
    {
        return 'DUPLICATE_COURSE_SPACE';
    }
}
