<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\AttendanceSummary;

/**
 * Book D ACA-04 §7/BR-ACA-04-013.
 */
final class ChronicAbsenteeIdentified
{
    public function __construct(public readonly AttendanceSummary $summary) {}
}
