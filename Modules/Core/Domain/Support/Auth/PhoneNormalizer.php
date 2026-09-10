<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Auth;

/**
 * Book A CORE-05 BR-CORE-05-003. Normalises Zimbabwean local formats
 * (`077...`, `07 7...`, `0771234567`) and bare national numbers to
 * E.164 (`+263771234567`). Numbers already in E.164 (or for another
 * country, `+...`) pass through unchanged.
 */
final class PhoneNormalizer
{
    public static function toE164(string $phone, string $defaultCountryCode = '263'): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '+')) {
            return $digits;
        }

        if (str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            return '+'.$defaultCountryCode.substr($digits, 1);
        }

        if (str_starts_with($digits, $defaultCountryCode)) {
            return '+'.$digits;
        }

        return '+'.$defaultCountryCode.$digits;
    }
}
