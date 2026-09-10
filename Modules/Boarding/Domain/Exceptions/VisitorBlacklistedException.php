<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book F BRD-03 §5/BR-BRD-03-018/AC-BRD-03-008.
 */
class VisitorBlacklistedException extends DomainException
{
    public static function forVisitor(int $visitorId): self
    {
        return new self(
            "Visitor #{$visitorId} is blacklisted and cannot be signed in.",
            ['visitor_id' => $visitorId],
        );
    }

    public function errorCode(): string
    {
        return 'VISITOR_BLACKLISTED';
    }
}
