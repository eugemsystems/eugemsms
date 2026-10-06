<?php

declare(strict_types=1);

namespace Modules\Academic\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\Models\Assignment;
use Modules\Academic\Models\AssignmentSubmission;
use Modules\Academic\Models\ContentItem;
use Modules\Academic\Models\CourseSpace;
use Modules\Academic\Models\TeachingGroupMember;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Http\Support\ApiResponse;
use Modules\People\Domain\Support\LinkedLearners;

/**
 * Read-only LMS for a guardian (and the learner themselves), Volume 1 §9.3 / Book K ACA-08:
 * `GET /api/v1/students/{student}/lms/courses` and `GET /api/v1/students/{student}/homework`.
 * Both resolve the learner through the signed-in user's own links, then the course spaces of the
 * teaching groups the learner currently belongs to, in the session term (`X-Term-Id`). Draft
 * assignments and unpublished content never appear; marks and feedback appear only once the
 * work is marked. Submitting and marking stay teacher/learner write paths and are not here.
 */
final class GuardianLmsController
{
    public function courses(Request $request, string $student, LinkedLearners $linked): JsonResponse
    {
        $link = $linked->linkFor($this->user($request), $student);
        abort_if($link === null, 404);

        $spaces = $this->spaces($link->student_id)->with(['subject:id,name', 'teachingGroup:id,name'])->get();
        $content = ContentItem::query()
            ->whereIn('course_space_id', $spaces->pluck('id'))
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->orderBy('sort_order')->orderBy('id')->get()->groupBy('course_space_id');

        return ApiResponse::ok($spaces->map(fn (CourseSpace $space): array => [
            'id' => $space->ulid,
            'subject' => $space->subject->name,
            'teaching_group' => $space->teachingGroup->name,
            'content' => $content->get($space->id, collect())->map(fn (ContentItem $item): array => [
                'id' => $item->ulid,
                'type' => $item->content_type,
                'title' => $item->title,
                'external_url' => $item->external_url,
                'size_bytes' => $item->file_size_bytes,
                'downloadable_offline' => $item->is_downloadable_offline,
            ])->values()->all(),
        ])->values()->all());
    }

    public function homework(Request $request, string $student, LinkedLearners $linked): JsonResponse
    {
        $link = $linked->linkFor($this->user($request), $student);
        abort_if($link === null, 404);

        $spaces = $this->spaces($link->student_id)->with('subject:id,name')->get()->keyBy('id');

        $page = Assignment::query()
            ->whereIn('course_space_id', $spaces->keys())
            ->whereIn('status', ['published', 'closed'])
            ->where('opens_at', '<=', now())
            ->orderByDesc('due_at')
            ->paginate(ApiResponse::perPage($request->integer('per_page') ?: null));

        $submissions = AssignmentSubmission::query()
            ->where('student_id', $link->student_id)
            ->whereIn('assignment_id', $page->getCollection()->pluck('id'))
            ->orderBy('attempt_number')->get()->groupBy('assignment_id')
            ->map(fn ($attempts) => $attempts->last());

        return ApiResponse::page($page->getCollection()->map(function (Assignment $assignment) use ($spaces, $submissions): array {
            /** @var AssignmentSubmission|null $submission */
            $submission = $submissions->get($assignment->id);
            $marked = $submission !== null && $submission->status === 'marked';

            return [
                'id' => $assignment->ulid,
                'subject' => $spaces->get($assignment->course_space_id)?->subject->name,
                'title' => $assignment->title,
                'instructions' => $assignment->instructions,
                'opens_at' => $assignment->opens_at->toIso8601ZuluString(),
                'due_at' => $assignment->due_at->toIso8601ZuluString(),
                'status' => $assignment->status,
                'max_mark' => $assignment->max_mark,
                'submission' => $submission === null ? null : [
                    'status' => $submission->status,
                    'attempt_number' => $submission->attempt_number,
                    'submitted_at' => $submission->submitted_at?->toIso8601ZuluString(),
                    'is_late' => $submission->is_late,
                    'final_mark' => $marked ? $submission->final_mark : null,
                    'feedback' => $marked ? $submission->feedback : null,
                ],
            ];
        })->values()->all(), $page);
    }

    /**
     * Active course spaces, in the session term, of the teaching groups the learner is in today.
     *
     * @return \Illuminate\Database\Eloquent\Builder<CourseSpace>
     */
    private function spaces(int $studentId): \Illuminate\Database\Eloquent\Builder
    {
        $today = now()->toDateString();
        $groupIds = TeachingGroupMember::query()
            ->where('student_id', $studentId)
            ->whereDate('effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
            ->select('teaching_group_id');

        return CourseSpace::query()
            ->where('term_id', (int) SessionContext::termId())
            ->where('is_active', true)
            ->whereIn('teaching_group_id', $groupIds);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
