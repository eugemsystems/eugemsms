<?php

declare(strict_types=1);

namespace Modules\People\Domain\Support;

use InvalidArgumentException;

/**
 * Book C PPL-03 §6/BR-PPL-03-013. Phone numbers are stored in E.164. A number
 * written the Zimbabwean way (`0771 234 567`) becomes `+263771234567`; one
 * already starting `+` or `00` is kept as international. Anything that is not
 * 8-15 digits is refused rather than stored half-right.
 */
final class PhoneNumberNormaliser
{
    public static function normalise(string $number, string $defaultCountryCode = '263'): string
    {
        $trimmed = trim($number);
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if (str_starts_with($trimmed, '+')) {
            $e164 = $digits;
        } elseif (str_starts_with($digits, '00')) {
            $e164 = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $e164 = $defaultCountryCode.substr($digits, 1);
        } else {
            $e164 = str_starts_with($digits, $defaultCountryCode) ? $digits : $defaultCountryCode.$digits;
        }

        if (strlen($e164) < 8 || strlen($e164) > 15) {
            throw new InvalidArgumentException("[{$number}] is not a valid phone number.");
        }

        return '+'.$e164;
    }
}
