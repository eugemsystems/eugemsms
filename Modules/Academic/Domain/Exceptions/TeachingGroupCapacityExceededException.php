<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book D ACA-02 §5/BR-ACA-02-012. Thrown when
 * `academic.enforce_teaching_group_capacity` is on and the assignment
 * would exceed `teaching_groups.capacity` — "a physical constraint,
 * not a preference".
 */
class TeachingGroupCapacityExceededException extends DomainException
{
    public static function forGroup(string $code, int $capacity, int $currentCount): self
    {
        return new self(
            "Teaching group {$code} is at capacity ({$currentCount}/{$capacity}).",
            ['code' => $code, 'capacity' => $capacity, 'current_count' => $currentCount],
        );
    }

    public function errorCode(): string
    {
        return 'TEACHING_GROUP_CAPACITY_EXCEEDED';
    }
}
