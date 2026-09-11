<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\PostDiscussionMessageData;
use Modules\Academic\Domain\Exceptions\DiscussionThreadLockedException;
use Modules\Academic\Models\DiscussionPost;
use Modules\Academic\Models\DiscussionThread;
use Modules\Core\Domain\Actions\Action;

final class PostDiscussionMessageAction extends Action
{
    public function execute(PostDiscussionMessageData $data): DiscussionPost
    {
        $thread = DiscussionThread::findOrFail($data->threadId);

        if ($thread->is_locked) {
            throw DiscussionThreadLockedException::forThread($thread->id);
        }

        return $this->transaction(fn (): DiscussionPost => DiscussionPost::create([
            'school_id' => $thread->school_id,
            'thread_id' => $thread->id,
            'posted_by_type' => $data->postedByType,
            'posted_by_id' => $data->postedById,
            'content' => $data->content,
            'is_hidden' => false,
            'posted_at' => Carbon::now(),
        ]));
    }
}
