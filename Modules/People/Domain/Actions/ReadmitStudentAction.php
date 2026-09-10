<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\ChangeStudentStatusData;
use Modules\People\Domain\DataObjects\ReadmitStudentData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentEnrolment;

/**
 * ACT-ReadmitStudent (Book C PPL-01 §5/BR-PPL-01-002/AC-PPL-01-008).
 * Reactivates a withdrawn learner and opens a fresh enrolment for the
 * target term — the `students` row, and therefore its `admission_number`
 * and full history, is never recreated. Issuing a second admission
 * number to the same person is exactly what this action exists to
 * prevent.
 */
final class ReadmitStudentAction extends Action
{
    public function __construct(
        private readonly ChangeStudentStatusAction $changeStatus,
    ) {}

    public function execute(ReadmitStudentData $data): Student
    {
        return $this->transaction(function () use ($data): Student {
            $student = $this->changeStatus->execute(new ChangeStudentStatusData(
                studentId: $data->studentId,
                newStatus: 'active',
                changedByUserId: $data->readmittedByUserId,
                reasonCode: 'readmission',
            ));

            $student->update(['exited_on' => null, 'updated_by' => $data->readmittedByUserId]);

            StudentEnrolment::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'section_id' => $student->section_id,
                'grade_level_id' => $student->grade_level_id,
                'class_id' => $student->class_id,
                'house_id' => $student->house_id,
                'enrolment_type' => $student->enrolment_type,
                'residency' => $student->residency,
                'pathway' => $student->pathway,
                'status' => 'active',
                'started_on' => now()->toDateString(),
                'is_repeat' => false,
                'created_by' => $data->readmittedByUserId,
            ]);

            return $student;
        });
    }
}
