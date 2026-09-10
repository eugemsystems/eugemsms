<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateTimetableConstraintData;
use Modules\Academic\Models\TimetableConstraint;
use Modules\Core\Domain\Actions\Action;

final class CreateTimetableConstraintAction extends Action
{
    public function execute(CreateTimetableConstraintData $data): TimetableConstraint
    {
        return $this->transaction(fn (): TimetableConstraint => TimetableConstraint::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'constraint_type' => $data->constraintType,
            'severity' => $data->severity,
            'weight' => $data->weight,
            'subject_id' => $data->subjectId,
            'staff_id' => $data->staffId,
            'class_id' => $data->classId,
            'venue_id' => $data->venueId,
            'grade_level_id' => $data->gradeLevelId,
            'cycle_days' => $data->cycleDays,
            'period_numbers' => $data->periodNumbers,
            'value' => $data->value,
            'reason' => $data->reason,
            'is_active' => true,
        ]));
    }
}
