<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H3 CMP-01 §3/BR-CMP-01-004/013 (AC-CMP-01-001). Export is
 * refused while any candidate has a validation error, while the
 * centre number is missing or inconsistent, or while unacknowledged
 * warnings exist.
 */
final class ZimsecExportBlockedException extends DomainException
{
    public static function hasErrors(int $registrationId, int $errorCount): self
    {
        return new self(
            "ZIMSEC registration #{$registrationId} cannot be exported: {$errorCount} candidate(s) still have validation errors.",
            ['registration_id' => $registrationId, 'error_count' => $errorCount],
        );
    }

    public static function centreNumberMissing(int $registrationId): self
    {
        return new self(
            "ZIMSEC registration #{$registrationId} cannot be exported: no centre number is set (BR-CMP-01-013).",
            ['registration_id' => $registrationId],
        );
    }

    public static function centreNumberInconsistent(int $registrationId, string $centreNumber, string $otherCentreNumber): self
    {
        return new self(
            "ZIMSEC registration #{$registrationId} cannot be exported: centre number '{$centreNumber}' conflicts with '{$otherCentreNumber}' already used for this school and exam level (BR-CMP-01-013).",
            ['registration_id' => $registrationId, 'centre_number' => $centreNumber, 'other_centre_number' => $otherCentreNumber],
        );
    }

    public static function unacknowledgedWarnings(int $registrationId, int $warningCount): self
    {
        return new self(
            "ZIMSEC registration #{$registrationId} has {$warningCount} candidate(s) with unacknowledged warnings — export again with acknowledgeWarnings to proceed.",
            ['registration_id' => $registrationId, 'warning_count' => $warningCount],
        );
    }

    public function errorCode(): string
    {
        return 'ZIMSEC_EXPORT_BLOCKED';
    }
}
