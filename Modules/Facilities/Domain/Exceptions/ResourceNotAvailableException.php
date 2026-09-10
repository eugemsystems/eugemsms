<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H2 OPS-05 §3/BR-OPS-05-001/002/AC-OPS-05-001/002. The message
 * is always `CheckResourceAvailabilityAction`'s own specific reason —
 * never a generic "not available" refusal.
 */
class ResourceNotAvailableException extends DomainException
{
    public static function forReason(int $resourceId, string $reason): self
    {
        return new self(
            "Resource #{$resourceId} is not available: {$reason}",
            ['resource_id' => $resourceId, 'reason' => $reason],
        );
    }

    public function errorCode(): string
    {
        return 'RESOURCE_NOT_AVAILABLE';
    }
}
