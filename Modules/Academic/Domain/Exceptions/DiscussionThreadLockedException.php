<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book K ACA-08 §2. A locked thread accepts no new posts.
 */
class DiscussionThreadLockedException extends DomainException
{
    public static function forThread(int $threadId): self
    {
        return new self(
            "Discussion thread #{$threadId} is locked and cannot accept new posts.",
            ['thread_id' => $threadId],
        );
    }

    public function errorCode(): string
    {
        return 'DISCUSSION_THREAD_LOCKED';
    }
}
