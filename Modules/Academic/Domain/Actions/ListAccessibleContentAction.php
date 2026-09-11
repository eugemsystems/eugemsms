<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Academic\Domain\DataObjects\ListAccessibleContentData;
use Modules\Academic\Domain\Exceptions\LearnerNotEnrolledException;
use Modules\Academic\Models\ContentItem;
use Modules\Academic\Models\CourseSpace;
use Modules\Academic\Models\TeachingGroupMember;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-ListAccessibleContent (Book K ACA-08 §4/BR-ACA-08-002/
 * AC-ACA-08-005). Read-only, but still an Action per BR-GLOBAL-001 —
 * every other module's enrolment-gated visibility works the same way.
 * Once a learner's `TeachingGroupMember` enrolment ends, this throws
 * rather than returning a filtered/empty list, so the caller can't
 * mistake "no access" for "nothing published yet".
 */
final class ListAccessibleContentAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return Collection<int, ContentItem>
     */
    public function execute(ListAccessibleContentData $data): Collection
    {
        $courseSpace = CourseSpace::findOrFail($data->courseSpaceId);

        if (! TeachingGroupMember::isActiveFor($data->studentId, $courseSpace->teaching_group_id)) {
            throw LearnerNotEnrolledException::forTeachingGroup($data->studentId, $courseSpace->teaching_group_id);
        }

        return ContentItem::query()
            ->where('course_space_id', $courseSpace->id)
            ->whereNotNull('published_at')
            ->orderBy('sort_order')
            ->get();
    }
}
