<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\File;
use Modules\People\Domain\DataObjects\RecordPriorSchoolData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentPriorSchool;

/**
 * ACT-RecordPriorSchool (Book C PPL-01 §5). One school a learner attended
 * before this one, with whether fees were left owing there.
 */
final class RecordPriorSchoolAction extends Action
{
    public const TYPES = ['government', 'council', 'mission', 'private', 'home_school', 'foreign'];

    public function execute(RecordPriorSchoolData $data): StudentPriorSchool
    {
        if (trim($data->schoolName) === '' || strlen($data->country) !== 2) {
            throw new InvalidArgumentException('A prior school needs a name and a two-letter country.');
        }

        if ($data->schoolType !== null && ! in_array($data->schoolType, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown school type [{$data->schoolType}].");
        }

        if ($data->attendedFrom !== null && $data->attendedTo !== null && $data->attendedTo->lt($data->attendedFrom)) {
            throw new InvalidArgumentException('The leaving date cannot be before the start date.');
        }

        if (! Student::query()->whereKey($data->studentId)->exists()
            || ($data->transferLetterFileId !== null && ! File::query()->whereKey($data->transferLetterFileId)->exists())) {
            throw new InvalidArgumentException('That learner or file does not belong to this school.');
        }

        return $this->transaction(fn (): StudentPriorSchool => StudentPriorSchool::create([
            'school_id' => $data->schoolId,
            'student_id' => $data->studentId,
            'school_name' => trim($data->schoolName),
            'school_type' => $data->schoolType,
            'country' => strtoupper($data->country),
            'province' => $data->province,
            'attended_from' => $data->attendedFrom?->toDateString(),
            'attended_to' => $data->attendedTo?->toDateString(),
            'last_grade_completed' => $data->lastGradeCompleted,
            'reason_for_leaving' => $data->reasonForLeaving,
            'transfer_letter_file_id' => $data->transferLetterFileId,
            'had_outstanding_fees' => $data->hadOutstandingFees,
            'notes' => $data->notes,
        ]));
    }
}
