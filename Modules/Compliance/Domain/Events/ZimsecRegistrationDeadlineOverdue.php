<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Events;

use Modules\Compliance\Models\ZimsecRegistration;

/**
 * Book H3 CMP-01 §3/BR-CMP-01-008. Fires every day the scan runs
 * while the registration is past `registration_closes_on` and still
 * not submitted — the recurrence is what makes the head's alert daily.
 */
final class ZimsecRegistrationDeadlineOverdue
{
    public function __construct(
        public readonly ZimsecRegistration $registration,
    ) {}
}
