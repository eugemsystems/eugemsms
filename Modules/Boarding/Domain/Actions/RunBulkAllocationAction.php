<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Boarding\Domain\DataObjects\RunBulkAllocationData;
use Modules\Boarding\Domain\Events\AllocationDraftReady;
use Modules\Boarding\Domain\Support\AllocationOutcome;
use Modules\Boarding\Domain\Support\BedAllocationEngine;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\LearnerIncompatibility;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Support\AccommodationConstraintResolver;

/**
 * ACT-RunBulkAllocation (Book F BRD-01 §3/§4 ⭐/BR-BRD-01-001/007/
 * AC-BRD-01-004). Every allocation this produces is a `draft` — never
 * confirmed here. Runs learner by learner in the order given (the
 * caller decides priority; continuity-eligible learners first is the
 * intended use, but this action does not itself reorder), and every
 * learner who could not be placed is returned with the blocking
 * reason named, never silently dropped. Gender segregation cannot be
 * violated by any input to this action — a student's candidate pool
 * is always filtered to their own gender's hostels before the engine
 * ever runs.
 */
final class RunBulkAllocationAction extends Action
{
    public function __construct(
        private readonly BedAllocationEngine $engine,
        private readonly SettingResolver $settings,
        private readonly AccommodationConstraintResolver $accommodationConstraints,
    ) {}

    /**
     * @return Collection<int, AllocationOutcome>
     */
    public function execute(RunBulkAllocationData $data): Collection
    {
        $incompatibilities = LearnerIncompatibility::query()
            ->where('school_id', $data->schoolId)
            ->where('is_active', true)
            ->get();

        $scope = new ScopeChain(schoolId: $data->schoolId);
        $maxLevelSpread = (int) $this->settings->get('boarding.max_level_spread_per_room', $scope);
        $preferHouseHostel = (bool) $this->settings->get('boarding.prefer_house_hostel', $scope);
        $preferSiblingTogether = (bool) $this->settings->get('boarding.siblings_same_hostel', $scope);
        $preferBedContinuity = (bool) $this->settings->get('boarding.prefer_bed_continuity', $scope);

        $outcomes = $this->transaction(function () use ($data, $incompatibilities, $maxLevelSpread, $preferHouseHostel, $preferSiblingTogether, $preferBedContinuity): Collection {
            $outcomes = collect();

            foreach ($data->studentIds as $studentId) {
                $student = Student::find($studentId);

                if ($student === null) {
                    continue;
                }

                if (! in_array($student->residency, ['BOARDER', 'WEEKLY_BOARDER'], true)) {
                    $outcomes->push(new AllocationOutcome($studentId, null, [], 'not_a_boarder'));

                    continue;
                }

                $alreadyAllocated = BedAllocation::query()
                    ->where('student_id', $studentId)
                    ->whereIn('status', ['confirmed', 'draft'])
                    ->whereNull('effective_to')
                    ->exists();

                if ($alreadyAllocated) {
                    $outcomes->push(new AllocationOutcome($studentId, null, [], 'already_allocated'));

                    continue;
                }

                $candidateBeds = $this->engine->candidateBedsFor($student->school_id, $student->gender);
                $accommodation = $this->accommodationConstraints->resolve($student->id);

                $outcome = $this->engine->findBedFor(
                    $student, $candidateBeds, $incompatibilities,
                    requiresGroundFloor: $accommodation['requiresGroundFloor'],
                    requiresExitProximity: $accommodation['requiresExitProximity'],
                    maxLevelSpread: $maxLevelSpread, preferHouseHostel: $preferHouseHostel,
                    preferSiblingTogether: $preferSiblingTogether, preferBedContinuity: $preferBedContinuity,
                );

                if ($outcome->isPlaced()) {
                    $bed = $outcome->bed;
                    $room = $bed->room;

                    BedAllocation::create([
                        'school_id' => $student->school_id,
                        'academic_year_id' => $data->academicYearId,
                        'term_id' => $data->termId,
                        'student_id' => $student->id,
                        'bed_id' => $bed->id,
                        'hostel_id' => $room->hostel_id,
                        'room_id' => $room->id,
                        'allocation_type' => 'initial',
                        'effective_from' => $data->effectiveFrom->toDateString(),
                        'status' => 'draft',
                        'allocated_by' => $data->allocatedByUserId,
                    ]);
                }

                $outcomes->push($outcome);
            }

            return $outcomes;
        });

        event(new AllocationDraftReady($data->schoolId, $data->termId, $outcomes));

        return $outcomes;
    }
}
