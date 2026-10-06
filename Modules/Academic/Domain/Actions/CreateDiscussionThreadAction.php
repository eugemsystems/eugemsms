<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\CreateDiscussionThreadData;
use Modules\Academic\Models\CourseSpace;
use Modules\Academic\Models\DiscussionThread;
use Modules\Core\Domain\Actions\Action;

final class CreateDiscussionThreadAction extends Action
{
    public function execute(CreateDiscussionThreadData $data): DiscussionThread
    {
        $courseSpace = CourseSpace::findOrFail($data->courseSpaceId);

        if (trim($data->title) === '' || mb_strlen($data->title) > 200) {
            throw new InvalidArgumentException('A thread needs a title of up to 200 characters.');
        }

        if (! $courseSpace->is_active) {
            throw new InvalidArgumentException('This course space is no longer active.');
        }

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
