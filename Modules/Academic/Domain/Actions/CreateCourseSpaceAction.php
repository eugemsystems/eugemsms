<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateCourseSpaceData;
use Modules\Academic\Domain\Exceptions\DuplicateCourseSpaceException;
use Modules\Academic\Models\CourseSpace;
use Modules\Academic\Models\TeachingGroup;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateCourseSpace (Book K ACA-08 §2/BR-ACA-08-001). A course
 * space borrows its subject/term/year straight from the teaching group
 * it mirrors — the caller supplies only the teaching group and an
 * optional teacher override, never a separate roster.
 */
final class CreateCourseSpaceAction extends Action
{
    public function execute(CreateCourseSpaceData $data): CourseSpace
    {
        $teachingGroup = TeachingGroup::findOrFail($data->teachingGroupId);

        $exists = CourseSpace::query()
            ->where('school_id', $teachingGroup->school_id)
            ->where('term_id', $teachingGroup->term_id)
            ->where('teaching_group_id', $teachingGroup->id)
            ->exists();

        if ($exists) {
            throw DuplicateCourseSpaceException::forTeachingGroup($teachingGroup->id, $teachingGroup->term_id);
        }

        return $this->transaction(fn (): CourseSpace => CourseSpace::create([
            'school_id' => $teachingGroup->school_id,
            'academic_year_id' => $teachingGroup->academic_year_id,
            'term_id' => $teachingGroup->term_id,
            'subject_id' => $teachingGroup->subject_id,
            'teaching_group_id' => $teachingGroup->id,
            'teacher_staff_id' => $data->teacherStaffId ?? $teachingGroup->teacher_staff_id,
            'banner_image_file_id' => $data->bannerImageFileId,
            'is_active' => true,
        ]));
    }
}
