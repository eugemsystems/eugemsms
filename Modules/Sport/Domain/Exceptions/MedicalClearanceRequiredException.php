<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Exceptions;

use RuntimeException;

/**
 * BR-OPS-07-002/AC-OPS-07-001.
 */
final class MedicalClearanceRequiredException extends RuntimeException
{
    public static function forStudent(int $studentId, int $activityId): self
    {
        return new self("Student #{$studentId} has a medical condition affecting physical activity and cannot be selected for activity #{$activityId} pending clearance (BR-OPS-07-002).");
    }
}
