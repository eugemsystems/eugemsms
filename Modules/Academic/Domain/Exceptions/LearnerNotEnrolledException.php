<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book K ACA-08 §4/BR-ACA-08-002/AC-ACA-08-005. Content and
 * assignments are visible to a learner only while they hold an active
 * `TeachingGroupMember` enrolment in that teaching group — consistent
 * with every other module's enrolment-gated visibility.
 */
class LearnerNotEnrolledException extends DomainException
{
    public static function forTeachingGroup(int $studentId, int $teachingGroupId): self
    {
        return new self(
            "Student #{$studentId} does not hold an active enrolment in teaching group #{$teachingGroupId}.",
            ['student_id' => $studentId, 'teaching_group_id' => $teachingGroupId],
        );
    }

    public function errorCode(): string
    {
        return 'LEARNER_NOT_ENROLLED';
    }
}
