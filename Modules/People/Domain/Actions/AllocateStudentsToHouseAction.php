<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\House;
use Modules\People\Domain\DataObjects\AllocateStudentsToHouseData;
use Modules\People\Domain\DataObjects\RecordStudentTimelineEventData;
use Modules\People\Models\Student;

/**
 * ACT-AllocateStudentsToHouse (Book C PPL-01 §2). Places learners in a school house, many at a
 * time. Only an active house of the school and active learners are accepted; a learner already in
 * the house is left as they are, and each change is written to the learner's timeline. Returns who
 * moved and who was skipped, with the reason, instead of failing the whole batch.
 */
final class AllocateStudentsToHouseAction extends Action
{
    public function __construct(
        private readonly RecordStudentTimelineEventAction $timeline,
    ) {}

    /**
     * @return array{allocated: array<int, int>, skipped: array<int, array{student_id: int, reason: string}>}
     */
    public function execute(AllocateStudentsToHouseData $data): array
    {
        $studentIds = array_values(array_unique($data->studentIds));

        if ($studentIds === [] || count($studentIds) > 300) {
            throw ValidationException::withMessages(['studentIds' => 'Choose between 1 and 300 learners.']);
        }

        $house = House::query()->find($data->houseId);

        if ($house === null || ! $house->is_active) {
            throw ValidationException::withMessages(['houseId' => 'Choose an active house of this school.']);
        }

        return $this->transaction(function () use ($data, $house, $studentIds): array {
            $students = Student::query()->whereIn('id', $studentIds)->get()->keyBy('id');
            $allocated = [];
            $skipped = [];

            foreach ($studentIds as $id) {
                $student = $students->get($id);

                $reason = match (true) {
                    $student === null || ! in_array($student->status, ['enrolled', 'active'], true) => 'Not a currently enrolled learner of this school.',
                    $student->house_id === $house->id => 'Already in this house.',
                    default => null,
                };

                if ($reason !== null) {
                    $skipped[] = ['student_id' => $id, 'reason' => $reason];

                    continue;
                }

                $student->update(['house_id' => $house->id]);
                $this->timeline->execute(new RecordStudentTimelineEventData($student->school_id, $student->id, 'administrative', 'house_allocated', "Placed in {$house->name} house", null, 'info', recordedByUserId: $data->allocatedByUserId));
                $allocated[] = $id;
            }

            return ['allocated' => $allocated, 'skipped' => $skipped];
        });
    }
}
