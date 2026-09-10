<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Exceptions;

use RuntimeException;

/**
 * BR-OPS-07-004.
 */
final class ActivityCapacityExceededException extends RuntimeException
{
    public static function forActivity(int $activityId, int $maxParticipants): self
    {
        return new self("Activity #{$activityId} is at its {$maxParticipants}-participant capacity — membership beyond this requires an override with a reason (BR-OPS-07-004).");
    }
}
