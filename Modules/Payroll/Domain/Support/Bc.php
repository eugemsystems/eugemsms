<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Support;

use UnexpectedValueException;

/**
 * A thin, validating narrower to `numeric-string` for bcmath calls
 * across this module — see `Modules\People\Domain\Support\LeaveDayMath`
 * for the same pattern applied to leave-day arithmetic. The
 * `is_numeric` guard is a genuine defensive check (a config or
 * computed value that isn't actually numeric should fail loudly, not
 * silently miscompute a statutory deduction), not merely a
 * type-narrowing trick — it happens to also satisfy PHPStan.
 */
final class Bc
{
    /**
     * @return numeric-string
     */
    public static function numeric(string $value): string
    {
        if (! is_numeric($value)) {
            throw new UnexpectedValueException("Expected a numeric string, got [{$value}].");
        }

        return $value;
    }
}
