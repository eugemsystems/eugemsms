<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\SuggestCoverData;
use Modules\Academic\Models\LessonSubstitution;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Staff;
use Modules\People\Models\TeacherAllocation;

/**
 * ACT-SuggestCover (Book E ACA-03 §6/BR-ACA-03-017). Two of the four
 * preference tiers the spec names: a free teacher of the subject,
 * then any other free teacher — both fairness-ranked by how many
 * substitutions each candidate has already covered this term, least
 * first. The "same department" tier and the deputy-head fallback are
 * not automated in this pass (no `subject → department` link exists
 * yet to derive the former from) — an empty result means the caller
 * picks manually, never a forced assignment.
 *
 * @return Collection<int, int> ordered candidate staff ids, best first
 */
final class SuggestCoverAction extends Action
{
    /**
     * @return Collection<int, int>
     */
    public function execute(SuggestCoverData $data): Collection
    {
        $substitution = LessonSubstitution::findOrFail($data->substitutionId);
        $slot = TimetableSlot::findOrFail($substitution->timetable_slot_id);

        $busyStaffIds = TimetableSlot::query()
            ->where('cycle_day', $slot->cycle_day)
            ->where('period_number', $slot->period_number)
            ->whereHas('timetable', fn ($q) => $q->where('status', 'published'))
            ->pluck('staff_id');

        $freeStaffIds = Staff::query()
            ->where('is_teaching', true)
            ->where('id', '!=', $substitution->absent_staff_id)
            ->whereNotIn('id', $busyStaffIds)
            ->pluck('id');

        $subjectTeacherIds = TeacherAllocation::query()
            ->where('subject_id', $slot->subject_id)
            ->where('status', 'active')
            ->pluck('staff_id')
            ->intersect($freeStaffIds)
            ->unique();

        $coverCounts = LessonSubstitution::query()
            ->where('term_id', $substitution->term_id)
            ->whereNotNull('cover_staff_id')
            ->selectRaw('cover_staff_id, count(*) as total')
            ->groupBy('cover_staff_id')
            ->pluck('total', 'cover_staff_id');

        $rank = fn (Collection $ids): Collection => $ids->sortBy(fn (int $id): int => (int) ($coverCounts[$id] ?? 0))->values();

        return $rank($subjectTeacherIds)
            ->merge($rank($freeStaffIds->diff($subjectTeacherIds)))
            ->values();
    }
}
