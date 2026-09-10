<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\TimetableClash;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\TeachingGroupMember;
use Modules\Academic\Models\TimetableSlot;

/**
 * Book E ACA-03 §3 ⭐. The four levels of clash detection: teacher,
 * venue, class — obvious — and learner, "the hard one": two teaching
 * groups in different subjects can each be clash-free at class level
 * while sharing learners, because A-Level option blocks and setted
 * teaching give individual learners their own effective timetable.
 * This runs identically for a full-timetable check (generation) and a
 * single proposed slot (manual edit) — `detectAmong()` is the shared
 * core both callers reduce to.
 */
final class TimetableClashDetector
{
    /**
     * @param  Collection<int, TimetableSlot>  $slots
     * @return Collection<int, TimetableClash>
     */
    public function detect(Collection $slots): Collection
    {
        $clashes = collect();

        foreach ($slots->groupBy(fn (TimetableSlot $s): string => "{$s->cycle_day}:{$s->period_number}") as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $clashes = $clashes->merge($this->detectAmong($group));
        }

        return $clashes->values();
    }

    /**
     * All clashes among a set of slots known to occupy the same
     * cycle day/period — used both for a full group (generation) and
     * for `[proposedSlot, ...concurrentSlots]` (a manual edit check).
     *
     * @param  Collection<int, TimetableSlot>  $group
     * @return Collection<int, TimetableClash>
     */
    public function detectAmong(Collection $group): Collection
    {
        $clashes = collect();

        $clashes = $clashes->merge($this->pairwiseSameValue($group, 'staff_id', 'teacher'));
        $clashes = $clashes->merge($this->pairwiseSameValue($group, 'venue_id', 'venue'));
        $clashes = $clashes->merge($this->pairwiseSameValue($group, 'class_id', 'class'));

        $rolls = $group->mapWithKeys(fn (TimetableSlot $s): array => [$s->id => $this->learnerIdsFor($s)]);

        foreach ($rolls as $slotIdA => $learnersA) {
            foreach ($rolls as $slotIdB => $learnersB) {
                if ($slotIdA >= $slotIdB) {
                    continue;
                }

                $shared = array_values(array_intersect($learnersA, $learnersB));

                if ($shared !== []) {
                    $clashes->push(new TimetableClash('learner', $slotIdA, $slotIdB, $shared));
                }
            }
        }

        return $clashes;
    }

    /**
     * @param  Collection<int, TimetableSlot>  $group
     * @return Collection<int, TimetableClash>
     */
    private function pairwiseSameValue(Collection $group, string $attribute, string $level): Collection
    {
        $clashes = collect();
        $byValue = $group->filter(fn (TimetableSlot $s): bool => $s->{$attribute} !== null)->groupBy($attribute);

        foreach ($byValue as $sameValueSlots) {
            if ($sameValueSlots->count() < 2) {
                continue;
            }

            $ids = $sameValueSlots->pluck('id')->values();

            for ($i = 0; $i < $ids->count(); $i++) {
                for ($j = $i + 1; $j < $ids->count(); $j++) {
                    $clashes->push(new TimetableClash($level, $ids[$i], $ids[$j]));
                }
            }
        }

        return $clashes;
    }

    /**
     * Every clash a proposed (not-yet-saved) slot would create against
     * the slots already occupying its cycle day/period — the check a
     * manual edit runs before it's ever written. `slotIdA` is always
     * `0` in the result, a sentinel for "the proposed slot", since it
     * has no id yet.
     *
     * @param  Collection<int, TimetableSlot>  $existingConcurrent
     * @return Collection<int, TimetableClash>
     */
    public function clashesForProposed(TimetableSlot $proposed, Collection $existingConcurrent): Collection
    {
        $clashes = collect();
        $proposedLearners = $this->learnerIdsFor($proposed);

        foreach ($existingConcurrent as $existing) {
            if ($proposed->staff_id === $existing->staff_id) {
                $clashes->push(new TimetableClash('teacher', 0, $existing->id));
            }

            if ($proposed->venue_id !== null && $proposed->venue_id === $existing->venue_id) {
                $clashes->push(new TimetableClash('venue', 0, $existing->id));
            }

            if ($proposed->class_id !== null && $proposed->class_id === $existing->class_id) {
                $clashes->push(new TimetableClash('class', 0, $existing->id));
            }

            $shared = array_values(array_intersect($proposedLearners, $this->learnerIdsFor($existing)));

            if ($shared !== []) {
                $clashes->push(new TimetableClash('learner', 0, $existing->id, $shared));
            }
        }

        return $clashes;
    }

    /**
     * @return array<int, int>
     */
    public function learnerIdsFor(TimetableSlot $slot): array
    {
        if ($slot->teaching_group_id !== null) {
            return TeachingGroupMember::query()
                ->where('teaching_group_id', $slot->teaching_group_id)
                ->whereNull('effective_to')
                ->pluck('student_id')
                ->all();
        }

        if ($slot->class_id !== null) {
            return ClassAllocation::query()
                ->where('class_id', $slot->class_id)
                ->where('term_id', $slot->term_id)
                ->where('status', 'confirmed')
                ->pluck('student_id')
                ->all();
        }

        return [];
    }
}
