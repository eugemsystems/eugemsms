<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\GenerateAttendanceSessionData;
use Modules\Academic\Models\AttendanceSession;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\TeachingGroupMember;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-GenerateAttendanceSession (Book D ACA-04 §2/§4/BR-ACA-04-002/003).
 * `expected_count` counts only learners with an active `ClassAllocation`
 * (form-class mode) or `TeachingGroupMember` (teaching-group mode) on
 * `session_date` — never a raw enrolment headcount, so a mid-term
 * transfer is reflected the same day it takes effect. Timetable-driven
 * generation (`ACA-03`) and the daily-schedule fallback are the
 * caller's own scheduling concern; this action creates exactly one
 * session for whatever scope it's given.
 *
 * `staffId`/`timetableSlotId` (Book E ACA-03 §5/BR-ACA-03-019) let
 * `GenerateAttendanceSessionsFromTimetableAction` stamp which teacher
 * and which lesson a period-mode session belongs to, so a later
 * substitution can move `staff_id` and the covering teacher sees the
 * register in their app — both are null for daily-mode/manually
 * generated sessions, unchanged from before this pair of columns
 * existed.
 */
final class GenerateAttendanceSessionAction extends Action
{
    public function execute(GenerateAttendanceSessionData $data): AttendanceSession
    {
        $expectedCount = $this->expectedCount($data);

        return $this->transaction(fn (): AttendanceSession => AttendanceSession::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'term_id' => $data->termId,
            'session_date' => $data->sessionDate->toDateString(),
            'mode' => $data->mode,
            'class_id' => $data->classId,
            'teaching_group_id' => $data->teachingGroupId,
            'subject_id' => $data->subjectId,
            'period_number' => $data->periodNumber,
            'staff_id' => $data->staffId,
            'timetable_slot_id' => $data->timetableSlotId,
            'expected_count' => $expectedCount,
            'present_count' => 0,
            'absent_count' => 0,
            'late_count' => 0,
            'excused_count' => 0,
            'status' => 'pending',
            'device_source' => $data->deviceSource,
        ]));
    }

    private function expectedCount(GenerateAttendanceSessionData $data): int
    {
        $on = $data->sessionDate->toDateString();

        if ($data->teachingGroupId !== null) {
            return TeachingGroupMember::query()
                ->where('teaching_group_id', $data->teachingGroupId)
                ->where('effective_from', '<=', $on)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $on))
                ->count();
        }

        if ($data->classId !== null) {
            return ClassAllocation::query()
                ->where('class_id', $data->classId)
                ->where('status', 'confirmed')
                ->where('effective_from', '<=', $on)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $on))
                ->count();
        }

        return 0;
    }
}
