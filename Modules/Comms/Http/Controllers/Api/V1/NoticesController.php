<?php

declare(strict_types=1);

namespace Modules\Comms\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Comms\Models\Notice;
use Modules\Core\Http\Support\ApiResponse;
use Modules\People\Domain\Support\LinkedLearners;
use Modules\People\Models\Student;

/**
 * `GET /api/v1/communications/notices` (Volume 1 §9.3). The notices a guardian is meant to see:
 * published, live now, and aimed at the whole school, or at a section or grade level one of
 * their linked learners belongs to. Staff-only notices never appear.
 */
final class NoticesController
{
    public function index(Request $request, LinkedLearners $linked): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $students = $linked->forUser($user)->map(fn ($link): Student => $link->student);
        $sectionIds = $students->pluck('section_id')->filter()->unique()->all();
        $levelIds = $students->pluck('grade_level_id')->filter()->unique()->all();

        $page = Notice::query()
            ->where('status', 'published')
            ->where('publish_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q
                ->where('audience_scope', 'whole_school')
                ->orWhere(fn ($q2) => $q2->where('audience_scope', 'section')->whereIn('audience_scope_id', $sectionIds))
                ->orWhere(fn ($q2) => $q2->where('audience_scope', 'level')->whereIn('audience_scope_id', $levelIds)))
            ->orderByDesc('is_pinned')
            ->orderByDesc('publish_at')
            ->paginate(ApiResponse::perPage($request->integer('per_page') ?: null));

        return ApiResponse::page($page->getCollection()->map(fn (Notice $notice): array => [
            'id' => $notice->ulid,
            'title' => $notice->title,
            'body' => $notice->body,
            'priority' => $notice->priority,
            'is_pinned' => $notice->is_pinned,
            'published_at' => $notice->publish_at->toIso8601ZuluString(),
        ])->values()->all(), $page);
    }
}
