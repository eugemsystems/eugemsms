<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use InvalidArgumentException;
use Modules\Boarding\Domain\DataObjects\AllocateBedData;
use Modules\Boarding\Domain\Events\BedAllocated;
use Modules\Boarding\Domain\Exceptions\GenderMismatchException;
use Modules\Boarding\Domain\Exceptions\NoBedAvailableException;
use Modules\Boarding\Domain\Support\BedAllocationEngine;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\LearnerIncompatibility;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Support\AccommodationConstraintResolver;

/**
 * ACT-AllocateBed (Book F BRD-01 §3/§4 ⭐/BR-BRD-01-001/004/005/007/
 * AC-BRD-01-001). Gender segregation is re-asserted here explicitly —
 * if the caller names a candidate hostel of the wrong gender, this
 * throws before the allocation engine ever runs, so the refusal is
 * unconditional at the Action layer, never merely a query that
 * happens to return nothing. `AccommodationConstraintResolver` (Book G
 * BRD-06) closes the medical-proximity/mobility hard constraints the
 * engine always accepted but this action never used to supply.
 */
final class AllocateBedAction extends Action
{
    public function __construct(
        private readonly BedAllocationEngine $engine,
        private readonly SettingResolver $settings,
        private readonly AccommodationConstraintResolver $accommodationConstraints,
    ) {}

    public function execute(AllocateBedData $data): BedAllocation
    {
        $student = Student::findOrFail($data->studentId);

        if (! in_array($student->residency, ['BOARDER', 'WEEKLY_BOARDER'], true)) {
            throw new InvalidArgumentException(
                "Student #{$student->id} has residency {$student->residency} and is not eligible for a bed allocation (BR-BRD-01-005).",
            );
        }

        $hasActiveAllocation = BedAllocation::query()
            ->where('student_id', $student->id)
            ->where('status', 'confirmed')
            ->whereNull('effective_to')
            ->exists();

        if ($hasActiveAllocation && $data->allocationType !== 'temporary') {
            throw new InvalidStateTransitionException(
                "Student #{$student->id} already holds an active bed allocation — use MoveLearnerAction to move them.",
                ['student_id' => $student->id],
            );
        }

        if ($data->candidateHostelIds !== []) {
            $mismatched = Hostel::query()
                ->whereIn('id', $data->candidateHostelIds)
                ->where('gender', '!=', $student->gender)
                ->exists();

            if ($mismatched) {
                throw GenderMismatchException::forHostel($student->id, $data->candidateHostelIds[0]);
            }
        }

        $candidateBeds = $this->engine->candidateBedsFor($student->school_id, $student->gender, $data->candidateHostelIds);

        $incompatibilities = LearnerIncompatibility::query()
            ->where('school_id', $student->school_id)
            ->where('is_active', true)
            ->get();

        $scope = new ScopeChain(schoolId: $student->school_id);
        $accommodation = $this->accommodationConstraints->resolve($student->id);
        $outcome = $this->engine->findBedFor(
            $student,
            $candidateBeds,
            $incompatibilities,
            requiresGroundFloor: $accommodation['requiresGroundFloor'],
            requiresExitProximity: $accommodation['requiresExitProximity'],
            maxLevelSpread: (int) $this->settings->get('boarding.max_level_spread_per_room', $scope),
            preferHouseHostel: (bool) $this->settings->get('boarding.prefer_house_hostel', $scope),
            preferSiblingTogether: (bool) $this->settings->get('boarding.siblings_same_hostel', $scope),
            preferBedContinuity: (bool) $this->settings->get('boarding.prefer_bed_continuity', $scope),
        );

        if (! $outcome->isPlaced()) {
            throw NoBedAvailableException::forStudent($student->id, $outcome->blockingReason ?? 'no_bed_available');
        }

        return $this->transaction(function () use ($student, $data, $outcome): BedAllocation {
            $bed = $outcome->bed;
            $room = $bed->room;

            $allocation = BedAllocation::create([
                'school_id' => $student->school_id,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'student_id' => $student->id,
                'bed_id' => $bed->id,
                'hostel_id' => $room->hostel_id,
                'room_id' => $room->id,
                'allocation_type' => $data->allocationType,
                'effective_from' => $data->effectiveFrom->toDateString(),
                'status' => $data->asDraft ? 'draft' : 'confirmed',
                'reason' => $data->reason,
                'allocated_by' => $data->allocatedByUserId,
            ]);

            if (! $data->asDraft) {
                event(new BedAllocated($allocation));
            }

            return $allocation;
        });
    }
}
