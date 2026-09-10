<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book D ACA-02 §5/BR-ACA-02-012. Thrown when
 * `academic.enforce_teaching_group_capacity` is off — capacity is
 * "always warned" even when not enforced, so the assignment must
 * carry `acknowledgeCapacityWarning` before it proceeds.
 */
class TeachingGroupCapacityRequiresAcknowledgementException extends DomainException
{
    public static function forGroup(string $code, int $capacity, int $currentCount): self
    {
        return new self(
            "Teaching group {$code} is over its capacity ({$currentCount}/{$capacity}) — acknowledge to proceed anyway.",
            ['code' => $code, 'capacity' => $capacity, 'current_count' => $currentCount],
        );
    }

    public function errorCode(): string
    {
        return 'TEACHING_GROUP_CAPACITY_REQUIRES_ACKNOWLEDGEMENT';
    }
}
