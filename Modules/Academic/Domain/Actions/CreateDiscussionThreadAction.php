<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateDiscussionThreadData;
use Modules\Academic\Models\CourseSpace;
use Modules\Academic\Models\DiscussionThread;
use Modules\Core\Domain\Actions\Action;

final class CreateDiscussionThreadAction extends Action
{
    public function execute(CreateDiscussionThreadData $data): DiscussionThread
    {
        $courseSpace = CourseSpace::findOrFail($data->courseSpaceId);

        return $this->transaction(fn (): DiscussionThread => DiscussionThread::create([
            'school_id' => $courseSpace->school_id,
            'course_space_id' => $courseSpace->id,
            'title' => $data->title,
            'created_by' => $data->createdByUserId,
            'is_locked' => false,
            'is_pinned' => false,
        ]));
    }
}
