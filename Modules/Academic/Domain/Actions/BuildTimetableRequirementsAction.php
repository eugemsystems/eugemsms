<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\BuildTimetableRequirementsData;
use Modules\Academic\Domain\DataObjects\TimetableRequirement;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\TeacherAllocation;

/**
 * ACT-BuildTimetableRequirements (Book E ACA-03 §4 step 1). Reads
 * `PPL-04`'s own `TeacherAllocation` rows — never a second source of
 * "who teaches what, how often" — for whole-class requirements.
 * Teaching-group (set) requirements are NOT built here:
 * `TeachingGroup` (Book D ACA-02) carries a `teacher_staff_id` but no
 * `periods_per_week`, so there is nothing to derive a period count
 * from yet. A school running setted teaching must add those
 * requirements manually in the grid editor until that column exists.
 */
final class BuildTimetableRequirementsAction extends Action
{
    /**
     * @return Collection<int, TimetableRequirement>
     */
    public function execute(BuildTimetableRequirementsData $data): Collection
    {
        return TeacherAllocation::query()
            ->where('academic_year_id', $data->academicYearId)
            ->where('term_id', $data->termId)
            ->where('status', 'active')
            ->get()
            ->map(fn (TeacherAllocation $allocation): TimetableRequirement => new TimetableRequirement(
                subjectId: $allocation->subject_id,
                staffId: $allocation->staff_id,
                periodsPerWeek: $allocation->weekly_periods,
                classId: $allocation->class_id,
            ));
    }
}
