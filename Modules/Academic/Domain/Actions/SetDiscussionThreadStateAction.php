<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Models\DiscussionThread;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-SetDiscussionThreadState (Book K ACA-08 §2/BR-ACA-08-010). Moderators
 * lock a thread (no further posts) or pin it. Neither deletes anything.
 */
final class SetDiscussionThreadStateAction extends Action
{
    public function execute(int $threadId, ?bool $isLocked = null, ?bool $isPinned = null): DiscussionThread
    {
        $thread = DiscussionThread::findOrFail($threadId);

        return $this->transaction(function () use ($thread, $isLocked, $isPinned): DiscussionThread {
            $thread->update(array_filter(['is_locked' => $isLocked, 'is_pinned' => $isPinned], fn (mixed $value): bool => $value !== null));

            return $thread->fresh();
        });
    }
}
