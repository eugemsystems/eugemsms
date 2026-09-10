<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\Staff;

/**
 * Consumed by `CORE-05` for token revocation, per the spec's events
 * list — this Action already revokes the tokens itself directly
 * (see `ProcessStaffExitAction`), so this event is informational for
 * any other listener (e.g. a future notification to IT).
 */
final class StaffExited
{
    public function __construct(
        public readonly Staff $staff,
    ) {}
}
