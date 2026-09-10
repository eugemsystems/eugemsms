<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Exceptions;

use RuntimeException;

/**
 * BR-OPS-07-001.
 */
final class GuardianConsentRequiredException extends RuntimeException
{
    public static function forActivity(int $activityId): self
    {
        return new self("Activity #{$activityId} requires guardian consent before membership is confirmed (BR-OPS-07-001).");
    }
}
