<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\ListNonSubmittersData;
use Modules\Academic\Models\Assignment;
use Modules\Academic\Models\AssignmentSubmission;
use Modules\Academic\Models\TeachingGroupMember;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Student;

/**
 * ACT-ListNonSubmitters (Book K ACA-08 §4/BR-ACA-08-009). A live list,
 * ready for one-tap chasing through `CORE-09` — see
 * `ChaseNonSubmittersAction`. Empty before the assignment's own
 * `due_at`, since "non-submission" isn't meaningful until then.
 */
final class ListNonSubmittersAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return Collection<int, Student>
     */
    public function execute(ListNonSubmittersData $data): Collection
    {
        $assignment = Assignment::with('courseSpace')->findOrFail($data->assignmentId);

        if (Carbon::now()->lessThan($assignment->due_at)) {
            return new Collection;
        }

        $submittedStudentIds = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->pluck('student_id');

        $enrolledStudentIds = TeachingGroupMember::query()
            ->where('teaching_group_id', $assignment->courseSpace->teaching_group_id)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', Carbon::today()))
            ->whereDate('effective_from', '<=', Carbon::today())
            ->pluck('student_id');

        return Student::query()
            ->whereIn('id', $enrolledStudentIds->diff($submittedStudentIds))
            ->get();
    }
}
