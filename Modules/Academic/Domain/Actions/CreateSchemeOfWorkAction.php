<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\CreateSchemeOfWorkData;
use Modules\Academic\Domain\Support\NormalisesPlannedTopics;
use Modules\Academic\Models\SchemeOfWork;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SyllabusCoverageRecord;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * ACT-CreateSchemeOfWork (Book K ACA-11 §2/BR-ACA-11-002). A
 * `SyllabusCoverageRecord` is created here for every planned topic,
 * indexed by its position in `planned_topics` — coverage tracking has
 * something to update the moment the scheme exists, not only once
 * it's approved.
 */
final class CreateSchemeOfWorkAction extends Action
{
    public function execute(CreateSchemeOfWorkData $data): SchemeOfWork
    {
        $topics = NormalisesPlannedTopics::normalise($data->plannedTopics);

        $term = Term::query()->whereKey($data->termId)->where('academic_year_id', $data->academicYearId)->first();

        if ($term === null
            || ! Subject::query()->whereKey($data->subjectId)->exists()
            || ! GradeLevel::query()->whereKey($data->gradeLevelId)->exists()
            || ! Staff::query()->whereKey($data->teacherStaffId)->exists()) {
            throw new InvalidArgumentException('The term, subject, grade level and teacher must all belong to this school.');
        }

        $duplicate = SchemeOfWork::query()
            ->where('term_id', $data->termId)->where('subject_id', $data->subjectId)
            ->where('grade_level_id', $data->gradeLevelId)->where('teacher_staff_id', $data->teacherStaffId)->exists();

        if ($duplicate) {
            throw new InvalidArgumentException('This teacher already has a scheme of work for that subject and grade level this term.');
        }

        return $this->transaction(function () use ($data, $topics): SchemeOfWork {
            $scheme = SchemeOfWork::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'subject_id' => $data->subjectId,
                'grade_level_id' => $data->gradeLevelId,
                'teacher_staff_id' => $data->teacherStaffId,
                'planned_topics' => $topics,
                'document_file_id' => $data->documentFileId,
                'status' => 'draft',
            ]);

            foreach (array_keys($topics) as $index) {
                SyllabusCoverageRecord::create([
                    'school_id' => $data->schoolId,
                    'scheme_of_work_id' => $scheme->id,
                    'planned_topic_index' => $index,
                ]);
            }

            return $scheme;
        });
    }
}
