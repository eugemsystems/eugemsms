<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H3 CMP-01 §3/BR-CMP-01-007 ⭐ (AC-CMP-01-002). Collections
 * against entry fees must reconcile to what's billed — a shortfall
 * blocks registration closure and is reported, since it's this
 * spec's own named "recurring source of unexplained deficit".
 */
final class ZimsecFeeShortfallException extends DomainException
{
    public static function forRegistration(int $registrationId, int $billedMinor, int $collectedMinor): self
    {
        $shortfall = $billedMinor - $collectedMinor;

        return new self(
            "ZIMSEC registration #{$registrationId} cannot be closed: entry fees billed total {$billedMinor} minor units but only {$collectedMinor} have been collected (shortfall of {$shortfall}).",
            ['registration_id' => $registrationId, 'billed_minor' => $billedMinor, 'collected_minor' => $collectedMinor, 'shortfall_minor' => $shortfall],
        );
    }

    public function errorCode(): string
    {
        return 'ZIMSEC_FEE_SHORTFALL';
    }
}
