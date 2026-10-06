<?php

declare(strict_types=1);

namespace Modules\Academic\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Academic\Domain\Actions\EnterMarkAction;
use Modules\Academic\Domain\Actions\SubmitAssessmentMarksAction;
use Modules\Academic\Domain\DataObjects\EnterMarkData;
use Modules\Academic\Domain\DataObjects\SubmitAssessmentMarksData;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentMark;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Http\Support\ApiResponse;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * `/api/v1/teacher/assessments` (Volume 1 §9.3, ACA-05). A teacher lists the assessments they may
 * still enter marks for, reads the roster with what is already saved, saves marks (each learner is
 * reported on separately, so one bad mark never loses the rest of an offline batch), and submits.
 * Entering overwrites freely while the assessment is draft or open — exactly as on the web screen —
 * so replaying a queued batch is harmless; once submitted, only the amendment workflow changes a mark.
 *
 * Reach follows `academic.result.enter`: section or school reach sees every open assessment; anything
 * narrower sees the ones for a subject the teacher teaches on the timetable, or that they created.
 */
final class TeacherMarksController
{
    public function index(Request $request, PermissionScopeResolver $resolver): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::ok($this->enterable($user, $resolver)->map(fn (Assessment $assessment): array => $this->summary($assessment))->values()->all());
    }

    public function show(Request $request, string $assessment, PermissionScopeResolver $resolver): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $found = $this->assessmentFor($user, $assessment, $resolver);
        $marks = AssessmentMark::query()->where('assessment_id', $found->id)->get()->keyBy('student_id');

        return ApiResponse::ok($this->summary($found) + [
            'learners' => $this->roster($found)->map(fn (Student $student): array => [
                'id' => $student->ulid,
                'admission_number' => $student->admission_number,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'raw_mark' => $marks->get($student->id)?->raw_mark,
                'is_absent' => (bool) $marks->get($student->id)?->is_absent,
            ])->values()->all(),
        ]);
    }

    public function save(Request $request, string $assessment, PermissionScopeResolver $resolver, EnterMarkAction $enter): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $found = $this->assessmentFor($user, $assessment, $resolver);
        $data = $request->validate([
            'marks' => ['required', 'array', 'min:1', 'max:300'],
            'marks.*.student' => ['required', 'string', 'max:40'],
            'marks.*.raw_mark' => ['nullable', 'numeric', 'min:0'],
            'marks.*.is_absent' => ['nullable', 'boolean'],
            'marks.*.absence_reason' => ['nullable', 'string', 'max:200'],
            'marks.*.comment' => ['nullable', 'string', 'max:500'],
        ]);

        $roster = $this->roster($found)->keyBy('ulid');
        $results = [];

        foreach ($data['marks'] as $row) {
            $student = $roster->get($row['student']);

            if ($student === null) {
                $results[] = ['student' => $row['student'], 'saved' => false, 'error' => 'This learner is not enrolled for the subject this term.'];

                continue;
            }

            try {
                $enter->execute(new EnterMarkData(
                    assessmentId: $found->id,
                    studentId: $student->id,
                    enteredByUserId: $user->id,
                    rawMark: ($row['is_absent'] ?? false) ? null : (isset($row['raw_mark']) ? (float) $row['raw_mark'] : null),
                    isAbsent: (bool) ($row['is_absent'] ?? false),
                    absenceReason: $row['absence_reason'] ?? null,
                    comment: $row['comment'] ?? null,
                ));
                $results[] = ['student' => $row['student'], 'saved' => true];
            } catch (DomainException|InvalidArgumentException $e) {
                $results[] = ['student' => $row['student'], 'saved' => false, 'error' => $e->getMessage()];
            }
        }

        return ApiResponse::ok(['results' => $results]);
    }

    public function submit(Request $request, string $assessment, PermissionScopeResolver $resolver, SubmitAssessmentMarksAction $submit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $found = $this->assessmentFor($user, $assessment, $resolver);

        return ApiResponse::ok($this->summary($submit->execute(new SubmitAssessmentMarksData($found->id, $user->id))));
    }

    private function assessmentFor(User $user, string $ulid, PermissionScopeResolver $resolver): Assessment
    {
        $found = $this->enterable($user, $resolver)->first(fn (Assessment $a): bool => $a->ulid === $ulid);
        abort_if($found === null, 404);

        return $found;
    }

    /**
     * @return Collection<int, Assessment>
     */
    private function enterable(User $user, PermissionScopeResolver $resolver): Collection
    {
        $scope = $resolver->resolve($user, 'academic.result.enter', SchoolContext::current()?->id);
        abort_if($scope === null, 403);

        $query = Assessment::query()->with('subject:id,name')->whereIn('status', ['draft', 'open'])
            ->where('term_id', (int) SessionContext::termId())->orderBy('assessed_on')->orderBy('title');

        if ($scope->isAtLeastAsWideAs(PermissionScope::Section)) {
            return $query->get();
        }

        $staffId = Staff::query()->where('user_id', $user->id)->value('id');
        $taught = $staffId === null ? [] : TimetableSlot::query()->where('staff_id', $staffId)->pluck('subject_id')->unique()->all();

        return $query->where(fn ($q) => $q->where('created_by', $user->id)->orWhereIn('subject_id', $taught))->get();
    }

    /**
     * @return Collection<int, Student>
     */
    private function roster(Assessment $assessment): Collection
    {
        return LearnerSubjectEnrolment::query()
            ->where('subject_id', $assessment->subject_id)
            ->where('term_id', $assessment->term_id)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter()
            ->sortBy('last_name')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Assessment $assessment): array
    {
        return [
            'id' => $assessment->ulid,
            'title' => $assessment->title,
            'subject' => $assessment->subject->name,
            'max_mark' => $assessment->max_mark,
            'assessed_on' => $assessment->assessed_on?->toDateString(),
            'status' => $assessment->status,
        ];
    }
}
