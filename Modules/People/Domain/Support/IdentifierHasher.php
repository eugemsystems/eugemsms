<?php

declare(strict_types=1);

namespace Modules\People\Domain\Support;

/**
 * Book C PPL-01 §4/BR-PPL-01-008. A deterministic hash of a normalised
 * identifier (national registration or birth certificate number),
 * maintained alongside the encrypted value so duplicates can be
 * detected without decrypting anything. Normalisation strips whitespace
 * and hyphens and uppercases, so `63-123456A12` and `63 123456 A 12`
 * hash identically.
 */
final class IdentifierHasher
{
    public static function hash(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return hash('sha256', self::normalise($value));
    }

    public static function normalise(string $value): string
    {
        return strtoupper(preg_replace('/[\s\-]+/', '', $value) ?? $value);
    }
}
