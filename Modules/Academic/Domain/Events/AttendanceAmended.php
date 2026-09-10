<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\AttendanceRecord;

/**
 * Book D ACA-04 §7/BR-ACA-04-008.
 */
final class AttendanceAmended
{
    public function __construct(public readonly AttendanceRecord $record) {}
}
