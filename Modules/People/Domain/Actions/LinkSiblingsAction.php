<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\LinkSiblingsData;
use Modules\People\Domain\Events\SiblingsLinked;
use Modules\People\Models\Student;
use Modules\People\Models\StudentSibling;

/**
 * ACT-LinkSiblings (Book C PPL-01 §5/BR-PPL-01-018). Linking A to B stores both
 * directions, so either learner's profile shows the other. Linking a learner to
 * themselves, or the same pair twice, is refused.
 */
final class LinkSiblingsAction extends Action
{
    public const RELATIONSHIPS = ['full', 'half', 'step', 'adopted', 'cousin_treated_as'];

    public function execute(LinkSiblingsData $data): void
    {
        if ($data->studentId === $data->siblingStudentId) {
            throw new InvalidArgumentException('A learner cannot be their own sibling.');
        }

        if (! in_array($data->relationship, self::RELATIONSHIPS, true)) {
            throw new InvalidArgumentException("Unknown sibling relationship [{$data->relationship}].");
        }

        $student = Student::findOrFail($data->studentId);
        $sibling = Student::findOrFail($data->siblingStudentId);

        if (StudentSibling::query()->where('student_id', $student->id)->where('sibling_student_id', $sibling->id)->exists()) {
            throw new InvalidArgumentException('These learners are already linked as siblings.');
        }

        $this->transaction(function () use ($student, $sibling, $data): void {
            foreach ([[$student, $sibling], [$sibling, $student]] as [$from, $to]) {
                StudentSibling::create([
                    'school_id' => $from->school_id,
                    'student_id' => $from->id,
                    'sibling_student_id' => $to->id,
                    'relationship' => $data->relationship,
                    'birth_order' => $from->id === $student->id ? $data->birthOrder : null,
                    'linked_by' => $data->linkedByUserId,
                    'created_at' => Carbon::now(),
                ]);
            }

            event(new SiblingsLinked($student, $sibling, true));
        });
    }
}
