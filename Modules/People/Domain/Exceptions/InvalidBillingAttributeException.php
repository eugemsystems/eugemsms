<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * BR-PPL-01-004. `ACT-ChangeBillingAttribute` only recognises the six
 * named billing attributes.
 */
class InvalidBillingAttributeException extends DomainException
{
    public static function forAttribute(string $attribute): self
    {
        return new self(
            "[{$attribute}] is not a billing attribute. Only enrolment_type, residency, grade_level, class, section, and pathway go through ACT-ChangeBillingAttribute.",
            ['attribute' => $attribute],
        );
    }

    public function errorCode(): string
    {
        return 'INVALID_BILLING_ATTRIBUTE';
    }
}
