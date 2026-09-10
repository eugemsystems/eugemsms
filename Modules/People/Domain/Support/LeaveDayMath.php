<?php

declare(strict_types=1);

namespace Modules\People\Domain\Support;

use UnexpectedValueException;

/**
 * Book C PPL-04 §4/BR-PPL-04-011. A thin, validating wrapper around
 * `bcmath` for the leave-balance buckets (`available_days`,
 * `pending_days`, `taken_days`) — all decimal(5,1) columns that
 * Eloquent's `decimal` cast returns as plain `string`, not the
 * `numeric-string` bcmath's own signatures require. The `is_numeric`
 * guard here is a genuine defensive check (a corrupted balance row
 * should fail loudly, not silently miscalculate), not merely a
 * type-narrowing trick — it happens to also satisfy PHPStan.
 */
final class LeaveDayMath
{
    public static function add(string $a, string $b): string
    {
        return bcadd(self::numeric($a), self::numeric($b), 1);
    }

    public static function subtract(string $a, string $b): string
    {
        return bcsub(self::numeric($a), self::numeric($b), 1);
    }

    public static function exceeds(string $requested, string $available): bool
    {
        return bccomp(self::numeric($requested), self::numeric($available), 1) > 0;
    }

    /**
     * @return numeric-string
     */
    private static function numeric(string $value): string
    {
        if (! is_numeric($value)) {
            throw new UnexpectedValueException("Expected a numeric string, got [{$value}].");
        }

        return $value;
    }
}
