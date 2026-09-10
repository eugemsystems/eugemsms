<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\AttendanceRecord;

/**
 * Book D ACA-04 §7 ⭐/BR-ACA-04-006. Fired regardless of whether the
 * guardian notification actually sends — the event is the audit
 * record that the school detected the absence; `DispatchNotificationAction`
 * (Book A CORE-09) decides independently whether the message itself
 * goes out (opt-out, preference, dedupe, quiet hours all apply there).
 */
final class LearnerAbsentUnexplained
{
    public function __construct(public readonly AttendanceRecord $record) {}
}
