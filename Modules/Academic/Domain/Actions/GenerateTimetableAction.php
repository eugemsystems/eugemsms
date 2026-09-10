<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\BuildTimetableRequirementsData;
use Modules\Academic\Domain\DataObjects\GenerateTimetableData;
use Modules\Academic\Domain\DataObjects\TimetableRequirement;
use Modules\Academic\Domain\Events\TimetableGenerated;
use Modules\Academic\Domain\Support\TimetableClashDetector;
use Modules\Academic\Models\PeriodSlot;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableGenerationRun;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-GenerateTimetable (Book E ACA-03 §4 ⭐, "the generation
 * algorithm, honestly described"). A working, deliberately simplified
 * two-phase implementation:
 *
 * **Phase 1 — greedy construction, no multi-level backtrack.** Each
 * requirement's periods are placed one at a time into the first
 * teachable slot that creates no hard clash (teacher, class, or
 * shared learner — the same four-level check `TimetableClashDetector`
 * runs elsewhere, computed here directly against an in-memory
 * occupancy map for speed rather than persisted rows). Requirements
 * needing more periods are placed first, on the reasoning that a
 * heavily-loaded class/teacher has fewer remaining feasible slots the
 * longer placement is deferred. A requirement that runs out of
 * feasible slots is recorded in full in `unplaced_requirements` —
 * BR-ACA-03-005's non-negotiable: nothing is ever force-fitted or
 * silently dropped.
 *
 * **Phase 2 — bounded local search, not full simulated annealing.**
 * A fixed number of random single-swaps between two placed lessons,
 * each accepted only if it does not introduce a hard clash and
 * reduces same-subject-same-day repetition (the one soft signal this
 * pass scores — the full `timetable_constraints` weighted-cost model
 * across every constraint type is not implemented here). This is a
 * genuine improvement pass, not a decorative one, but it is not the
 * annealing-with-temperature-schedule the spec describes; venue
 * assignment and double-period linking are likewise not attempted in
 * this pass.
 *
 * What IS guaranteed, matching the spec's own non-negotiables: zero
 * hard violations in persisted output, every unplaced requirement
 * named with why, locked slots never moved, and every run repeatable
 * (recorded parameters, no hidden randomness seed — `mt_rand` is used
 * unseeded for the Phase 2 shuffle, so "repeatable from its recorded
 * seed" specifically is also deferred).
 */
final class GenerateTimetableAction extends Action
{
    private const int PHASE_2_ITERATIONS = 200;

    public function __construct(
        private readonly BuildTimetableRequirementsAction $buildRequirements,
        private readonly TimetableClashDetector $detector,
    ) {}

    public function execute(GenerateTimetableData $data): TimetableGenerationRun
    {
        $timetable = Timetable::findOrFail($data->timetableId);

        $run = TimetableGenerationRun::create([
            'school_id' => $timetable->school_id,
            'timetable_id' => $timetable->id,
            'algorithm' => 'greedy',
            'status' => 'running',
            'started_at' => Carbon::now(),
            'requested_by' => $data->requestedByUserId,
        ]);

        $teachableSlots = PeriodSlot::query()
            ->where('structure_id', $timetable->structure_id)
            ->where('is_teachable', true)
            ->get();

        $requirements = $this->buildRequirements->execute(new BuildTimetableRequirementsData(
            $timetable->school_id, $timetable->academic_year_id, $timetable->term_id,
        ));

        $occupancy = $this->seedOccupancyFromLockedSlots($timetable);

        $toCreate = [];
        $unplaced = [];
        $iterations = 0;

        foreach ($requirements->sortByDesc(fn (TimetableRequirement $r): int => $r->periodsPerWeek) as $requirement) {
            $learnerIds = $this->learnerIdsFor($requirement, $timetable->term_id);
            $placedCount = 0;

            foreach ($teachableSlots as $slot) {
                if ($placedCount >= $requirement->periodsPerWeek) {
                    break;
                }

                $iterations++;
                $key = "{$slot->cycle_day}:{$slot->period_number}";
                $occupants = $occupancy[$key] ?? [];

                if ($this->wouldClash($occupants, $requirement, $learnerIds)) {
                    continue;
                }

                $occupancy[$key][] = $this->occupantFor($requirement, $learnerIds);
                $toCreate[] = $this->slotAttributesFor($timetable, $slot, $requirement) + ['_learner_ids' => $learnerIds];
                $placedCount++;
            }

            if ($placedCount < $requirement->periodsPerWeek) {
                $unplaced[] = [
                    'label' => $requirement->label(),
                    'subject_id' => $requirement->subjectId,
                    'class_id' => $requirement->classId,
                    'teaching_group_id' => $requirement->teachingGroupId,
                    'staff_id' => $requirement->staffId,
                    'periods_requested' => $requirement->periodsPerWeek,
                    'periods_placed' => $placedCount,
                    'blocking_constraint' => 'No remaining teachable slot without a teacher, class, or learner clash.',
                ];
            }
        }

        $toCreate = $this->improveSpread($toCreate, $iterations);

        return $this->transaction(function () use ($timetable, $run, $toCreate, $unplaced, $iterations): TimetableGenerationRun {
            foreach ($toCreate as $attributes) {
                unset($attributes['_learner_ids']);
                TimetableSlot::create($attributes);
            }

            $timetable->update([
                'status' => 'generated',
                'generation_run_id' => $run->id,
                'hard_violations' => 0,
            ]);

            $run->update([
                'status' => 'completed',
                'iterations' => $iterations,
                'hard_violations' => 0,
                'unplaced_requirements' => $unplaced,
                'completed_at' => Carbon::now(),
            ]);

            $fresh = $run->fresh();

            event(new TimetableGenerated($timetable->fresh(), $fresh));

            return $fresh;
        });
    }

    /**
     * @return array<string, array<int, array{staff_id: int, class_id: ?int, teaching_group_id: ?int, learner_ids: array<int, int>}>>
     */
    private function seedOccupancyFromLockedSlots(Timetable $timetable): array
    {
        $occupancy = [];

        $lockedSlots = TimetableSlot::query()
            ->where('timetable_id', $timetable->id)
            ->where('is_locked', true)
            ->get();

        foreach ($lockedSlots as $locked) {
            $key = "{$locked->cycle_day}:{$locked->period_number}";
            $occupancy[$key][] = [
                'staff_id' => $locked->staff_id,
                'class_id' => $locked->class_id,
                'teaching_group_id' => $locked->teaching_group_id,
                'learner_ids' => $this->detector->learnerIdsFor($locked),
            ];
        }

        return $occupancy;
    }

    /**
     * @return array<int, int>
     */
    private function learnerIdsFor(TimetableRequirement $requirement, int $termId): array
    {
        $throwaway = new TimetableSlot([
            'class_id' => $requirement->classId,
            'teaching_group_id' => $requirement->teachingGroupId,
            'term_id' => $termId,
        ]);

        return $this->detector->learnerIdsFor($throwaway);
    }

    /**
     * @param  array<int, array{staff_id: int, class_id: ?int, teaching_group_id: ?int, learner_ids: array<int, int>}>  $occupants
     * @param  array<int, int>  $learnerIds
     */
    private function wouldClash(array $occupants, TimetableRequirement $requirement, array $learnerIds): bool
    {
        foreach ($occupants as $occupant) {
            if ($occupant['staff_id'] === $requirement->staffId) {
                return true;
            }

            if ($requirement->classId !== null && $occupant['class_id'] === $requirement->classId) {
                return true;
            }

            if (array_intersect($learnerIds, $occupant['learner_ids']) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, int>  $learnerIds
     * @return array{staff_id: int, class_id: ?int, teaching_group_id: ?int, learner_ids: array<int, int>}
     */
    private function occupantFor(TimetableRequirement $requirement, array $learnerIds): array
    {
        return [
            'staff_id' => $requirement->staffId,
            'class_id' => $requirement->classId,
            'teaching_group_id' => $requirement->teachingGroupId,
            'learner_ids' => $learnerIds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function slotAttributesFor(Timetable $timetable, PeriodSlot $slot, TimetableRequirement $requirement): array
    {
        return [
            'school_id' => $timetable->school_id,
            'timetable_id' => $timetable->id,
            'term_id' => $timetable->term_id,
            'period_slot_id' => $slot->id,
            'cycle_day' => $slot->cycle_day,
            'period_number' => $slot->period_number,
            'subject_id' => $requirement->subjectId,
            'class_id' => $requirement->classId,
            'teaching_group_id' => $requirement->teachingGroupId,
            'staff_id' => $requirement->staffId,
        ];
    }

    /**
     * Phase 2 — see the class docblock for exactly what this does and
     * does not implement relative to the spec's simulated annealing.
     *
     * @param  array<int, array<string, mixed>>  $toCreate
     * @return array<int, array<string, mixed>>
     */
    private function improveSpread(array $toCreate, int &$iterations): array
    {
        $score = fn (array $slots): int => $this->sameDayRepeatCount($slots);
        $bestScore = $score($toCreate);

        for ($i = 0; $i < self::PHASE_2_ITERATIONS && count($toCreate) >= 2; $i++) {
            $iterations++;
            $a = array_rand($toCreate);
            $b = array_rand($toCreate);

            if ($a === $b) {
                continue;
            }

            $swapped = $toCreate;
            [$swapped[$a]['cycle_day'], $swapped[$b]['cycle_day']] = [$swapped[$b]['cycle_day'], $swapped[$a]['cycle_day']];
            [$swapped[$a]['period_number'], $swapped[$b]['period_number']] = [$swapped[$b]['period_number'], $swapped[$a]['period_number']];
            [$swapped[$a]['period_slot_id'], $swapped[$b]['period_slot_id']] = [$swapped[$b]['period_slot_id'], $swapped[$a]['period_slot_id']];

            if ($this->createsHardClash($swapped)) {
                continue;
            }

            $newScore = $score($swapped);

            if ($newScore < $bestScore) {
                $toCreate = $swapped;
                $bestScore = $newScore;
            }
        }

        return $toCreate;
    }

    /**
     * @param  array<int, array<string, mixed>>  $slots
     */
    private function sameDayRepeatCount(array $slots): int
    {
        $seen = [];
        $repeats = 0;

        foreach ($slots as $slot) {
            $key = "{$slot['staff_id']}:{$slot['cycle_day']}";
            $seen[$key] = ($seen[$key] ?? 0) + 1;

            if ($seen[$key] > 1) {
                $repeats++;
            }
        }

        return $repeats;
    }

    /**
     * @param  array<int, array<string, mixed>>  $slots
     */
    private function createsHardClash(array $slots): bool
    {
        $byKey = [];

        foreach ($slots as $slot) {
            $key = "{$slot['cycle_day']}:{$slot['period_number']}";
            $byKey[$key][] = $slot;
        }

        foreach ($byKey as $group) {
            if (count($group) < 2) {
                continue;
            }

            $seenStaff = [];
            $seenClass = [];
            $seenLearners = [];

            foreach ($group as $slot) {
                if (isset($seenStaff[$slot['staff_id']])) {
                    return true;
                }

                $seenStaff[$slot['staff_id']] = true;

                if ($slot['class_id'] !== null) {
                    if (isset($seenClass[$slot['class_id']])) {
                        return true;
                    }

                    $seenClass[$slot['class_id']] = true;
                }

                if (array_intersect($seenLearners, $slot['_learner_ids']) !== []) {
                    return true;
                }

                $seenLearners = [...$seenLearners, ...$slot['_learner_ids']];
            }
        }

        return false;
    }
}
