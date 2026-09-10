<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book C PPL-04 §4/BR-PPL-04-006 (AC-PPL-04-001). Only thrown when
 * `staff.enforce_workload_ceiling` is on — otherwise the allocation
 * proceeds and the overload is left for the workload report to
 * surface.
 */
class WorkloadCeilingExceededException extends DomainException
{
    public static function forStaff(int $staffId, int $currentPeriods, int $additionalPeriods, int $ceiling): self
    {
        $proposed = $currentPeriods + $additionalPeriods;

        return new self(
            "Staff member [{$staffId}] has {$currentPeriods} periods; adding {$additionalPeriods} would bring them to {$proposed}, over the ceiling of {$ceiling}.",
            ['staff_id' => $staffId, 'current_periods' => $currentPeriods, 'additional_periods' => $additionalPeriods, 'ceiling' => $ceiling],
        );
    }

    public function errorCode(): string
    {
        return 'WORKLOAD_CEILING_EXCEEDED';
    }
}
