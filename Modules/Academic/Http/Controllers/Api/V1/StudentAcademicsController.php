<?php

declare(strict_types=1);

namespace Modules\Academic\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\AttendanceSummary;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Http\Support\ApiResponse;
use Modules\People\Domain\Support\LinkedLearners;

/**
 * `GET /api/v1/students/{student}/attendance` and `.../timetable` (Volume 1 §9.3). Both are read in
 * the session context the request resolved (the current term unless `X-Term-Id` says otherwise),
 * and only for a learner the signed-in guardian is currently linked to. The timetable is the
 * published one: a draft is never shown.
 */
final class StudentAcademicsController
{
    public function attendance(Request $request, string $student, LinkedLearners $linked): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $link = $linked->linkFor($user, $student);
        abort_if($link === null, 404);

        $termId = (int) SessionContext::termId();

        $summaries = AttendanceSummary::query()
            ->where('student_id', $link->student_id)
            ->where('term_id', $termId)
            ->get()
            ->map(fn (AttendanceSummary $summary): array => [
                'scope' => $summary->scope,
                'subject_id' => $summary->subject_id,
                'sessions_expected' => $summary->sessions_expected,
                'present' => $summary->present_count,
                'absent_authorised' => $summary->absent_authorised,
                'absent_unauthorised' => $summary->absent_unauthorised,
                'late' => $summary->late_count,
                'attendance_percent' => $summary->attendance_percent,
            ])->values()->all();

        $recent = AttendanceRecord::query()
            ->where('student_id', $link->student_id)
            ->where('term_id', $termId)
            ->orderByDesc('session_date')
            ->limit(30)
            ->get()
            ->map(fn (AttendanceRecord $record): array => [
                'date' => $record->session_date->toDateString(),
                'status' => $record->status,
                'minutes_late' => $record->minutes_late,
            ])->values()->all();

        return ApiResponse::ok(['term_id' => $termId, 'summaries' => $summaries, 'recent' => $recent]);
    }

    public function timetable(Request $request, string $student, LinkedLearners $linked): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $link = $linked->linkFor($user, $student);
        abort_if($link === null, 404);

        $timetable = Timetable::query()
            ->where('term_id', (int) SessionContext::termId())
            ->where('status', 'published')
            ->orderByDesc('version')
            ->first();

        $classId = $link->student->class_id;

        if ($timetable === null || $classId === null) {
            return ApiResponse::ok(['timetable_id' => $timetable?->ulid, 'slots' => []]);
        }

        $slots = TimetableSlot::query()
            ->where('timetable_id', $timetable->id)
            ->where('class_id', $classId)
            ->with(['periodSlot', 'subject:id,name', 'venue:id,name'])
            ->get()
            ->sortBy(fn (TimetableSlot $slot): string => sprintf('%02d-%02d', $slot->cycle_day, $slot->period_number));

        return ApiResponse::ok([
            'timetable_id' => $timetable->ulid,
            'slots' => $slots->map(fn (TimetableSlot $slot): array => [
                'cycle_day' => $slot->cycle_day,
                'period_number' => $slot->period_number,
                'starts_at' => $slot->periodSlot->starts_at,
                'ends_at' => $slot->periodSlot->ends_at,
                'subject' => $slot->subject->name,
                'venue' => $slot->venue?->name,
            ])->values()->all(),
        ]);
    }
}
