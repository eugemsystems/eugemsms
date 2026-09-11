<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateSchemeOfWorkData;
use Modules\Academic\Models\SchemeOfWork;
use Modules\Academic\Models\SyllabusCoverageRecord;
use Modules\Core\Domain\Actions\Action;

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
        return $this->transaction(function () use ($data): SchemeOfWork {
            $scheme = SchemeOfWork::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'subject_id' => $data->subjectId,
                'grade_level_id' => $data->gradeLevelId,
                'teacher_staff_id' => $data->teacherStaffId,
                'planned_topics' => $data->plannedTopics,
                'document_file_id' => $data->documentFileId,
                'status' => 'draft',
            ]);

            foreach (array_keys($data->plannedTopics) as $index) {
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
