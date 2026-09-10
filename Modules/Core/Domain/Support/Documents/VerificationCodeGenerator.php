<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Documents;

/**
 * Book A CORE-06 §2/BR-CORE-06-012. Short, unambiguous (no 0/O/1/I),
 * URL-safe code for `/verify/{code}`.
 */
final class VerificationCodeGenerator
{
    private const string ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const int LENGTH = 10;

    public function generate(): string
    {
        $code = '';
        $alphabetLength = strlen(self::ALPHABET);

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $code;
    }
}
