<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Events;

use Modules\Compliance\Models\ZimsecRegistration;

/**
 * Book H3 CMP-01 §3/BR-CMP-01-008. Fires once per configured alert
 * day (30, 14, 7, 1 by default) while the registration is not yet
 * submitted.
 */
final class ZimsecRegistrationDeadlineDue
{
    public function __construct(
        public readonly ZimsecRegistration $registration,
        public readonly int $daysUntilDue,
    ) {}
}
