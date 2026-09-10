<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\EstablishmentPost;
use Modules\People\Models\Staff;

/**
 * ACT-BuildStaffEstablishmentSnapshot (Book H3 CMP-02 §3/BR-CMP-02-004).
 * Approved posts, filled posts and vacancies come straight from
 * `EstablishmentPost` — the only place this codebase tracks them
 * (Book C PPL-04). `qualifications_tracked` is explicitly `false`:
 * `Staff` defers `staff_qualifications` entirely, so there is no
 * qualification record to report — this flag makes that gap visible
 * on the return rather than a silently blank field.
 */
final class BuildStaffEstablishmentSnapshotAction extends Action
{
    /**
     * @return array{approved_posts: int, filled_posts: int, vacancies: int, qualifications_tracked: bool, by_post: array<int, array{post_id: int, title: string, approved_count: int, filled_count: int}>, teaching_staff_without_registration_no: int}
     */
    public function execute(int $schoolId): array
    {
        $posts = EstablishmentPost::where('school_id', $schoolId)->where('is_active', true)->get();

        return [
            'approved_posts' => (int) $posts->sum('approved_count'),
            'filled_posts' => (int) $posts->sum('filled_count'),
            'vacancies' => (int) $posts->sum(fn (EstablishmentPost $post): int => max(0, $post->approved_count - $post->filled_count)),
            'qualifications_tracked' => false,
            'by_post' => $posts->map(fn (EstablishmentPost $post): array => [
                'post_id' => $post->id,
                'title' => $post->title,
                'approved_count' => $post->approved_count,
                'filled_count' => $post->filled_count,
            ])->values()->all(),
            'teaching_staff_without_registration_no' => Staff::where('school_id', $schoolId)->where('status', 'active')->where('is_teaching', true)->whereNull('teacher_registration_no')->count(),
        ];
    }
}
