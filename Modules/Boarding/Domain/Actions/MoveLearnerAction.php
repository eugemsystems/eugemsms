<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\MoveLearnerData;
use Modules\Boarding\Domain\Events\LearnerMovedRoom;
use Modules\Boarding\Domain\Exceptions\GenderMismatchException;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\HostelBed;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Student;

/**
 * ACT-MoveLearner (Book F BRD-01 §4/BR-BRD-01-001/006 ⭐). Mid-term
 * movement supersedes rather than overwrites: the old row is ended
 * (`effective_to` set, `status` moved to `superseded`), and a new row
 * is created for the new bed — the full history stays queryable,
 * including who moved whom and why. Gender is re-checked here too,
 * independently of `AllocateBedAction` — there is no path into a
 * mismatched hostel from either entry point.
 */
final class MoveLearnerAction extends Action
{
    public function execute(MoveLearnerData $data): BedAllocation
    {
        $student = Student::findOrFail($data->studentId);
        $newBed = HostelBed::findOrFail($data->newBedId);
        $newRoom = $newBed->room;
        $newHostel = $newRoom->hostel;

        if ($newHostel->gender !== $student->gender) {
            throw GenderMismatchException::forHostel($student->id, $newHostel->id);
        }

        $current = BedAllocation::query()
            ->where('student_id', $student->id)
            ->where('status', 'confirmed')
            ->whereNull('effective_to')
            ->first();

        if ($current === null) {
            throw new InvalidStateTransitionException(
                "Student #{$student->id} has no active bed allocation to move from.",
                ['student_id' => $student->id],
            );
        }

        $bedOccupied = BedAllocation::query()
            ->where('bed_id', $newBed->id)
            ->where('status', 'confirmed')
            ->whereNull('effective_to')
            ->exists();

        if ($bedOccupied) {
            throw new InvalidStateTransitionException(
                "Bed #{$newBed->id} is already occupied.",
                ['bed_id' => $newBed->id],
            );
        }

        return $this->transaction(function () use ($student, $current, $newBed, $newRoom, $newHostel, $data): BedAllocation {
            $current->update(['status' => 'superseded', 'effective_to' => $data->effectiveFrom->copy()->subDay()->toDateString()]);

            $moved = BedAllocation::create([
                'school_id' => $student->school_id,
                'academic_year_id' => $current->academic_year_id,
                'term_id' => $current->term_id,
                'student_id' => $student->id,
                'bed_id' => $newBed->id,
                'hostel_id' => $newHostel->id,
                'room_id' => $newRoom->id,
                'allocation_type' => $data->allocationType,
                'effective_from' => $data->effectiveFrom->toDateString(),
                'status' => 'confirmed',
                'reason' => $data->reason,
                'allocated_by' => $data->movedByUserId,
                'confirmed_by' => $data->movedByUserId,
            ]);

            event(new LearnerMovedRoom($current, $moved));

            return $moved;
        });
    }
}
