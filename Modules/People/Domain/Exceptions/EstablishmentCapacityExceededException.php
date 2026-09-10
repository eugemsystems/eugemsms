<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book C PPL-04 §4/BR-PPL-04-004. Filling a post beyond its
 * `approved_count` requires `staff.override_establishment` and a
 * reason — a school that quietly overstaffs cannot budget.
 */
class EstablishmentCapacityExceededException extends DomainException
{
    public static function forPost(int $postId): self
    {
        return new self(
            "Establishment post [{$postId}] has no remaining approved places — override with a reason, or increase the approved count first.",
            ['post_id' => $postId],
        );
    }

    public function errorCode(): string
    {
        return 'ESTABLISHMENT_CAPACITY_EXCEEDED';
    }
}
