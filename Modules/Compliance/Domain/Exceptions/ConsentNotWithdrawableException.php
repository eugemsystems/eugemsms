<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H3 CMP-03 §3/BR-CMP-03-004. A consent whose type's lawful
 * basis is `legal_obligation` or `vital_interest` is recorded but not
 * withdrawable — the basis is displayed to the subject instead.
 */
final class ConsentNotWithdrawableException extends DomainException
{
    public static function forConsent(int $consentId, string $lawfulBasis): self
    {
        return new self(
            "Consent #{$consentId} cannot be withdrawn: its type's lawful basis is '{$lawfulBasis}'.",
            ['consent_id' => $consentId, 'lawful_basis' => $lawfulBasis],
        );
    }

    public function errorCode(): string
    {
        return 'CONSENT_NOT_WITHDRAWABLE';
    }
}
