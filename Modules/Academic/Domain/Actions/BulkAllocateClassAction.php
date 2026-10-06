<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Academic\Domain\DataObjects\AllocateClassData;
use Modules\Academic\Domain\DataObjects\BulkAllocateClassData;
use Modules\Academic\Models\ClassAllocation;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\SchoolClass;
use Modules\People\Models\Student;

/**
 * ACT-BulkAllocateClass (Book D ACA-02 §5/BR-ACA-02-004/014). Places many learners in one class
 * for a term in one step, through `AllocateClassAction` for each so every learner keeps its
 * supersede-and-auto-enrol behaviour. A learner is skipped, with the reason returned, rather than
 * failing the batch, when: they are not an learner of this school who is currently enrolled, their grade level is not
 * the class's, they are already in the class, or the class is full. A class's capacity is a limit,
 * so learners beyond it are skipped in the order given, never silently over-filled.
 */
final class BulkAllocateClassAction extends Action
{
    public function __construct(
        private readonly AllocateClassAction $allocate,
    ) {}

    /**
     * @return array{allocated: array<int, int>, skipped: array<int, array{student_id: int, reason: string}>}
     */
    public function execute(BulkAllocateClassData $data): array
    {
        $studentIds = array_values(array_unique($data->studentIds));

        if ($studentIds === [] || count($studentIds) > 300) {
            throw ValidationException::withMessages(['studentIds' => 'Choose between 1 and 300 learners.']);
        }

        $class = SchoolClass::query()->findOrFail($data->classId);

        return $this->transaction(function () use ($data, $class, $studentIds): array {
            $inClass = ClassAllocation::query()->where('class_id', $class->id)->where('term_id', $data->termId)->where('status', 'confirmed')->pluck('student_id')->all();
            $room = max(0, $class->capacity - count($inClass));
            $students = Student::query()->whereIn('id', $studentIds)->get()->keyBy('id');
            $allocated = [];
            $skipped = [];

            foreach ($studentIds as $id) {
                $student = $students->get($id);

                $reason = match (true) {
                    $student === null || ! in_array($student->status, ['enrolled', 'active'], true) => 'Not a currently enrolled learner of this school.',
                    $student->grade_level_id !== $class->grade_level_id => 'The learner\'s grade level is not this class\'s.',
                    in_array($id, $inClass, true) => 'Already in this class.',
                    $room <= 0 => 'The class is full.',
                    default => null,
                };

                if ($reason !== null) {
                    $skipped[] = ['student_id' => $id, 'reason' => $reason];

                    continue;
                }

                $this->allocate->execute(new AllocateClassData($id, $class->id, $data->termId, $data->allocatedByUserId, $data->effectiveFrom, $data->allocationType));
                $allocated[] = $id;
                $room--;
            }

            return ['allocated' => $allocated, 'skipped' => $skipped];
        });
    }
}
