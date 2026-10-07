<?php

declare(strict_types=1);

namespace Modules\Academic\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\AttendanceSummary;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Http\Support\ApiResponse;
use Modules\People\Models\Student;

/**
 * `GET /api/v1/me/{subjects,attendance,results}` (Book D ACA-02 §7/ACA-04 §6/ACA-05 §7). The same
 * ergonomic shortcut `Modules\People\Http\Controllers\Api\V1\LearnerProfileController::show()`
 * already set up for `/me/profile`: a `Student` row whose own `user_id` is this token's user,
 * rather than asking a learner app to already know its own ulid before it can call
 * `StudentAcademicsController`'s equivalents. `results` is published-only and age-gated the same
 * way a guardian's own view is (BR-ACA-05-023: a learner sees results only where
 * `academic.publish_to_learner_portal` is on for their level band) — read live from the setting
 * each call, never cached, since a school can flip the setting at any time.
 */
final class LearnerSelfController
{
    public function subjects(Request $request): JsonResponse
    {
        $student = $this->currentLearner($request);
        $termId = (int) SessionContext::termId();

        $enrolments = LearnerSubjectEnrolment::query()
            ->where('student_id', $student->id)
            ->where('term_id', $termId)
            ->where('status', 'active')
            ->with('subject:id,ulid,name')
            ->get();

        return ApiResponse::ok($enrolments->map(fn (LearnerSubjectEnrolment $e): array => [
            'subject' => ['id' => $e->subject->ulid, 'name' => $e->subject->name],
            'effective_from' => $e->effective_from->toDateString(),
        ])->values()->all());
    }

    public function attendance(Request $request): JsonResponse
    {
        $student = $this->currentLearner($request);
        $termId = (int) SessionContext::termId();

        $summaries = AttendanceSummary::query()->where('student_id', $student->id)->where('term_id', $termId)->get();
        $recent = AttendanceRecord::query()->where('student_id', $student->id)->where('term_id', $termId)->orderByDesc('session_date')->limit(30)->get();

        return ApiResponse::ok([
            'term_id' => $termId,
            'summaries' => $summaries->map(fn (AttendanceSummary $s): array => [
                'scope' => $s->scope, 'subject_id' => $s->subject_id, 'attendance_percent' => $s->attendance_percent,
            ])->values()->all(),
            'recent' => $recent->map(fn (AttendanceRecord $r): array => ['date' => $r->session_date->toDateString(), 'status' => $r->status])->values()->all(),
        ]);
    }

    public function results(Request $request): JsonResponse
    {
        $student = $this->currentLearner($request);

        if (! $this->publishedToLearnerPortal($student)) {
            return ApiResponse::ok([]);
        }

        $results = TermResult::query()->where('student_id', $student->id)->where('status', 'published')->orderByDesc('term_id')->get();

        return ApiResponse::ok($results->map(fn (TermResult $r): array => [
            'id' => $r->ulid,
            'term_id' => $r->term_id,
            'average_percent' => $r->average_percent,
            'class_position' => $r->class_position,
            'class_size' => $r->class_size,
            'published_at' => $r->published_at?->toIso8601ZuluString(),
        ])->values()->all());
    }

    private function currentLearner(Request $request): Student
    {
        /** @var User $user */
        $user = $request->user();

        $student = Student::query()->where('user_id', $user->id)->where('status', 'active')->first();
        abort_if($student === null, 404);

        return $student;
    }

    private function publishedToLearnerPortal(Student $student): bool
    {
        return (bool) app(SettingResolver::class)->get('academic.publish_to_learner_portal', new ScopeChain(schoolId: $student->school_id));
    }
}
