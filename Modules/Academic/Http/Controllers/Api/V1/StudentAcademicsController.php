<?php

declare(strict_types=1);

namespace Modules\Academic\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\Domain\Support\SubjectEnrolmentQuery;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\AttendanceSummary;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectEnrolmentChange;
use Modules\Academic\Models\TermSubjectResult;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Core\Models\Term;
use Modules\People\Domain\Support\LinkedLearners;

/**
 * `GET /api/v1/students/{student}/{attendance,timetable,subjects,subject-history,performance-trend}`
 * (Volume 1 §9.3, Book D ACA-02 §7/ACA-05 §7). Every read here is scoped to a learner the signed-in
 * guardian (or the learner themselves, via `LinkedLearners`'s own self-link) is currently linked
 * to. `attendance`/`timetable` read in the session context the request resolved (the current term
 * unless `X-Term-Id` says otherwise); the timetable is the published one, a draft is never shown.
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

    public function subjects(Request $request, string $student, LinkedLearners $linked): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $link = $linked->linkFor($user, $student);
        abort_if($link === null, 404);

        $termId = (int) SessionContext::termId();

        $enrolments = LearnerSubjectEnrolment::query()
            ->where('student_id', $link->student_id)
            ->where('term_id', $termId)
            ->where('status', 'active')
            ->with('subject:id,ulid,name')
            ->get();

        return ApiResponse::ok($enrolments->map(fn (LearnerSubjectEnrolment $e): array => [
            'subject' => ['id' => $e->subject->ulid, 'name' => $e->subject->name],
            'effective_from' => $e->effective_from->toDateString(),
            'enrolment_reason' => $e->enrolment_reason,
        ])->values()->all());
    }

    public function subjectHistory(Request $request, string $student, LinkedLearners $linked, SubjectEnrolmentQuery $query): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $link = $linked->linkFor($user, $student);
        abort_if($link === null, 404);

        $term = Term::query()->findOrFail((int) SessionContext::termId());
        $changes = $query->changesInTerm($link->student, $term);
        $subjectNames = Subject::query()->whereIn('id', $changes->pluck('subject_id'))->pluck('name', 'id');

        return ApiResponse::ok($changes->map(fn (SubjectEnrolmentChange $c): array => [
            'subject' => $subjectNames[$c->subject_id] ?? null,
            'change_type' => $c->change_type,
            'effective_from' => $c->effective_from->toDateString(),
            'reason' => $c->reason,
        ])->values()->all());
    }

    public function performanceTrend(Request $request, string $student, LinkedLearners $linked): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $link = $linked->linkFor($user, $student);
        abort_if($link === null, 404);

        $data = $request->validate(['subject' => ['required', 'string'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $subjectId = Subject::query()->where('ulid', $data['subject'])->value('id');
        abort_if($subjectId === null, 404);

        $results = TermSubjectResult::query()
            ->where('student_id', $link->student_id)
            ->where('subject_id', $subjectId)
            ->whereNotNull('final_percent')
            ->with('term:id,ulid,name,starts_on')
            ->get()
            ->filter(function (TermSubjectResult $r) use ($data): bool {
                $startsOn = $r->term->starts_on->toDateString();

                return (! isset($data['from']) || $startsOn >= $data['from']) && (! isset($data['to']) || $startsOn <= $data['to']);
            })
            ->sortBy(fn (TermSubjectResult $r): string => $r->term->starts_on->toDateString());

        return ApiResponse::ok($results->map(fn (TermSubjectResult $r): array => [
            'term' => ['id' => $r->term->ulid, 'name' => $r->term->name],
            'final_percent' => $r->final_percent,
            'grade' => $r->grade,
            'class_position' => $r->class_position,
        ])->values()->all());
    }
}
