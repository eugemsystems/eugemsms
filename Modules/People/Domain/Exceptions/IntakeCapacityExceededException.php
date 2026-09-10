<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book C PPL-02 §4/BR-PPL-02-008. Conversion is refused unless the
 * intake's grade level still has capacity, unless overridden with
 * `admissions.override_capacity` and a reason.
 */
class IntakeCapacityExceededException extends DomainException
{
    public static function forIntake(int $intakeId): self
    {
        return new self(
            "Intake [{$intakeId}] has no remaining capacity — override with a reason, or release a place first.",
            ['intake_id' => $intakeId],
        );
    }

    public function errorCode(): string
    {
        return 'INTAKE_CAPACITY_EXCEEDED';
    }
}
