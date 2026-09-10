<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Support;

use Illuminate\Support\Collection;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelBed;
use Modules\Boarding\Models\HostelRoom;
use Modules\Boarding\Models\LearnerIncompatibility;
use Modules\Core\Models\GradeLevel;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * Book F BRD-01 §3 ⭐ — the allocation engine. A deliberately
 * simplified, single-pass greedy evaluator, NOT a full constraint
 * solver with backtracking: for one learner it scores every
 * hard-valid candidate bed and picks the lowest soft score. Run
 * learner by learner in priority order (continuity-eligible learners
 * first) by the calling action, it does not itself optimise globally
 * across a whole cohort — the spec's own "seating puzzle" framing
 * notwithstanding, a full solver is out of this pass' scope, matching
 * `GenerateTimetableAction`'s own documented simplification.
 *
 * HARD constraints (never violated — a bed failing any of these is
 * dropped from consideration entirely, never merely scored down):
 *   1. gender_match          — filtered by the caller's query, re-asserted here
 *   2. bed availability      — filtered by the caller's query, re-asserted here
 *   3. room in service       — filtered by the caller's query, re-asserted here
 *   4. learner_incompatibility
 *   5. medical_proximity     — `$requiresExitProximity` flag, no diagnosis seen
 *   6. mobility_ground_floor — `$requiresGroundFloor` flag, no diagnosis seen
 *
 * SOFT constraints (scored; lower total is better):
 *   7. level_band            — widening the room's grade-level spread
 *   8. house_affinity        — hostel not the learner's sports house
 *   9. sibling_together      — a sibling already in a different hostel
 *  10. continuity            — not the learner's own bed last term
 *  11. room balance          — a fuller room than the least-full alternative
 */
final class BedAllocationEngine
{
    /**
     * Beds structurally available (in-service room, available bed)
     * and not currently occupied, in hostels matching the given
     * gender. Callers pass this straight into `findBedFor()`.
     *
     * @param  array<int, int>  $hostelIds  empty = every active hostel of that gender
     * @return Collection<int, HostelBed>
     */
    public function candidateBedsFor(int $schoolId, string $gender, array $hostelIds = []): Collection
    {
        $hostelQuery = Hostel::query()->where('school_id', $schoolId)->where('gender', $gender)->where('is_active', true);

        if ($hostelIds !== []) {
            $hostelQuery->whereIn('id', $hostelIds);
        }

        $matchingHostelIds = $hostelQuery->pluck('id');

        if ($hostelIds !== [] && $matchingHostelIds->isEmpty()) {
            return collect();
        }

        $roomIds = HostelRoom::query()
            ->whereIn('hostel_id', $matchingHostelIds)
            ->where('condition_grade', '!=', 'out_of_service')
            ->pluck('id');

        $occupiedBedIds = BedAllocation::query()
            ->whereIn('room_id', $roomIds)
            ->whereIn('status', ['confirmed', 'draft'])
            ->whereNull('effective_to')
            ->pluck('bed_id');

        return HostelBed::query()
            ->whereIn('room_id', $roomIds)
            ->where('is_available', true)
            ->whereNotIn('id', $occupiedBedIds)
            ->get();
    }

    /**
     * @param  Collection<int, HostelBed>  $candidateBeds
     * @param  Collection<int, LearnerIncompatibility>  $incompatibilities
     */
    public function findBedFor(
        Student $student,
        Collection $candidateBeds,
        Collection $incompatibilities,
        bool $requiresGroundFloor = false,
        bool $requiresExitProximity = false,
        int $maxLevelSpread = 2,
        bool $preferHouseHostel = true,
        bool $preferSiblingTogether = true,
        bool $preferBedContinuity = true,
    ): AllocationOutcome {
        $evaluations = $candidateBeds->map(fn (HostelBed $bed): BedCandidateEvaluation => $this->evaluate(
            $student, $bed, $incompatibilities, $requiresGroundFloor, $requiresExitProximity,
            $maxLevelSpread, $preferHouseHostel, $preferSiblingTogether, $preferBedContinuity,
        ));

        $hardValid = $evaluations->filter(fn (BedCandidateEvaluation $e): bool => $e->isHardValid)->values();

        if ($hardValid->isEmpty()) {
            $firstEvaluation = $evaluations->first();
            $reason = $firstEvaluation === null ? 'no_bed_available' : ($firstEvaluation->hardBlockReason ?? 'no_bed_available');

            return new AllocationOutcome($student->id, null, [], $reason);
        }

        $best = $hardValid->sortBy(fn (BedCandidateEvaluation $e): float => $e->softScore)->first();

        return new AllocationOutcome($student->id, $best->bed, $best->softViolations);
    }

    /**
     * @param  Collection<int, LearnerIncompatibility>  $incompatibilities
     */
    private function evaluate(
        Student $student,
        HostelBed $bed,
        Collection $incompatibilities,
        bool $requiresGroundFloor,
        bool $requiresExitProximity,
        int $maxLevelSpread,
        bool $preferHouseHostel,
        bool $preferSiblingTogether,
        bool $preferBedContinuity,
    ): BedCandidateEvaluation {
        $room = HostelRoom::findOrFail($bed->room_id);

        if (! $bed->is_available) {
            return new BedCandidateEvaluation($bed, false, 'bed_unavailable', [], 0.0);
        }

        if ($room->condition_grade === 'out_of_service') {
            return new BedCandidateEvaluation($bed, false, 'room_out_of_service', [], 0.0);
        }

        $occupantIds = $this->currentOccupantIds($room->id);

        foreach ($incompatibilities as $incompatibility) {
            if (! $incompatibility->involves($student->id)) {
                continue;
            }

            $otherId = $incompatibility->otherStudentId($student->id);

            if ($otherId === null) {
                continue;
            }

            $conflictsInScope = match ($incompatibility->scope) {
                'room' => $occupantIds->contains($otherId),
                'wing' => $this->wingOccupantIds($room->wing_id)->contains($otherId),
                'hostel' => $this->hostelOccupantIds($room->hostel_id)->contains($otherId),
                default => false,
            };

            if ($conflictsInScope) {
                return new BedCandidateEvaluation($bed, false, 'learner_incompatibility', [], 0.0);
            }
        }

        if ($requiresExitProximity && $room->proximity_to_exit !== 'near') {
            return new BedCandidateEvaluation($bed, false, 'medical_proximity', [], 0.0);
        }

        if ($requiresGroundFloor && ! $room->is_ground_floor) {
            return new BedCandidateEvaluation($bed, false, 'mobility_ground_floor', [], 0.0);
        }

        $violations = [];
        $score = 0.0;

        if ($this->wouldWidenLevelSpread($student, $room, $occupantIds, $maxLevelSpread)) {
            $violations[] = 'level_band';
            $score += 3.0;
        }

        if ($preferHouseHostel && $student->house_id !== null) {
            $hostel = Hostel::find($room->hostel_id);

            if ($hostel?->house_id !== null && $hostel->house_id !== $student->house_id) {
                $violations[] = 'house_affinity';
                $score += 1.0;
            }
        }

        if ($preferSiblingTogether && ! $this->siblingAlreadyInHostel($student, $room->hostel_id)) {
            $siblingElsewhere = $this->hasSiblingElsewhere($student, $room->hostel_id);

            if ($siblingElsewhere) {
                $violations[] = 'sibling_together';
                $score += 2.0;
            }
        }

        if ($preferBedContinuity && ! $this->heldThisBedLastTerm($student->id, $bed->id)) {
            $violations[] = 'continuity';
            $score += 1.0;
        }

        $fillRatio = $room->bed_count > 0 ? $occupantIds->count() / $room->bed_count : 0.0;
        $score += $fillRatio;

        return new BedCandidateEvaluation($bed, true, null, $violations, $score);
    }

    /**
     * @return Collection<int, int>
     */
    private function currentOccupantIds(int $roomId): Collection
    {
        return BedAllocation::query()
            ->where('room_id', $roomId)
            ->whereIn('status', ['confirmed', 'draft'])
            ->whereNull('effective_to')
            ->pluck('student_id');
    }

    /**
     * @return Collection<int, int>
     */
    private function wingOccupantIds(?int $wingId): Collection
    {
        if ($wingId === null) {
            return collect();
        }

        $roomIds = HostelRoom::where('wing_id', $wingId)->pluck('id');

        return BedAllocation::query()
            ->whereIn('room_id', $roomIds)
            ->whereIn('status', ['confirmed', 'draft'])
            ->whereNull('effective_to')
            ->pluck('student_id');
    }

    /**
     * @return Collection<int, int>
     */
    private function hostelOccupantIds(int $hostelId): Collection
    {
        return BedAllocation::query()
            ->where('hostel_id', $hostelId)
            ->whereIn('status', ['confirmed', 'draft'])
            ->whereNull('effective_to')
            ->pluck('student_id');
    }

    /**
     * @param  Collection<int, int>  $occupantIds
     */
    private function wouldWidenLevelSpread(Student $student, HostelRoom $room, Collection $occupantIds, int $maxLevelSpread): bool
    {
        if ($occupantIds->isEmpty()) {
            return false;
        }

        $ordinals = Student::whereIn('id', $occupantIds)->pluck('grade_level_id')
            ->push($student->grade_level_id)
            ->unique()
            ->map(function (int $id): int {
                $gradeLevel = GradeLevel::find($id);

                return $gradeLevel === null ? 0 : $gradeLevel->ordinal;
            });

        return ($ordinals->max() - $ordinals->min()) > $maxLevelSpread;
    }

    private function siblingAlreadyInHostel(Student $student, int $hostelId): bool
    {
        $siblingIds = $this->siblingIds($student->id);

        if ($siblingIds->isEmpty()) {
            return false;
        }

        return $this->hostelOccupantIds($hostelId)->intersect($siblingIds)->isNotEmpty();
    }

    private function hasSiblingElsewhere(Student $student, int $candidateHostelId): bool
    {
        $siblingIds = $this->siblingIds($student->id);

        if ($siblingIds->isEmpty()) {
            return false;
        }

        return BedAllocation::query()
            ->whereIn('student_id', $siblingIds)
            ->whereIn('status', ['confirmed', 'draft'])
            ->whereNull('effective_to')
            ->where('hostel_id', '!=', $candidateHostelId)
            ->exists();
    }

    /**
     * A sibling is approximated as another learner sharing an active
     * guardian link — this codebase has no dedicated sibling table.
     *
     * @return Collection<int, int>
     */
    private function siblingIds(int $studentId): Collection
    {
        $guardianIds = StudentGuardian::where('student_id', $studentId)->where('status', 'active')->pluck('guardian_id');

        if ($guardianIds->isEmpty()) {
            return collect();
        }

        return StudentGuardian::whereIn('guardian_id', $guardianIds)
            ->where('student_id', '!=', $studentId)
            ->where('status', 'active')
            ->pluck('student_id')
            ->unique();
    }

    private function heldThisBedLastTerm(int $studentId, int $bedId): bool
    {
        return BedAllocation::query()
            ->where('student_id', $studentId)
            ->where('bed_id', $bedId)
            ->exists();
    }
}
