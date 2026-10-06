<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\PostDiscussionMessageData;
use Modules\Academic\Domain\Exceptions\DiscussionThreadLockedException;
use Modules\Academic\Models\CourseSpace;
use Modules\Academic\Models\DiscussionPost;
use Modules\Academic\Models\DiscussionThread;
use Modules\Academic\Models\TeachingGroupMember;
use Modules\Core\Domain\Actions\Action;

final class PostDiscussionMessageAction extends Action
{
    public function execute(PostDiscussionMessageData $data): DiscussionPost
    {
        $thread = DiscussionThread::findOrFail($data->threadId);

        if (trim($data->content) === '' || mb_strlen($data->content) > 5000) {
            throw new InvalidArgumentException('A post needs content of up to 5,000 characters.');
        }

        // Authorship is checked against the course space itself: a staff poster must be its
        // teacher and a student poster an active member — never an id the caller picks freely.
        $courseSpace = CourseSpace::findOrFail($thread->course_space_id);

        $isMember = match ($data->postedByType) {
            'staff' => $courseSpace->teacher_staff_id === $data->postedById,
            'student' => TeachingGroupMember::isActiveFor($data->postedById, $courseSpace->teaching_group_id),
            default => false,
        };

        if (! $isMember) {
            throw new InvalidArgumentException('Only the course teacher or an enrolled learner can post here.');
        }

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
