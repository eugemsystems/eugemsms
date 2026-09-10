<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-07-014 — a step with `requires_comment` rejects an approval
 * action submitted without one.
 */
class CommentRequiredException extends DomainException
{
    public function errorCode(): string
    {
        return 'COMMENT_REQUIRED';
    }
}
