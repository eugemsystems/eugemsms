<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\UnlinkSiblingsData;
use Modules\People\Domain\Events\SiblingsLinked;
use Modules\People\Models\Student;
use Modules\People\Models\StudentSibling;

/**
 * ACT-UnlinkSiblings (Book C PPL-01 §5/BR-PPL-01-018). Removes both directions
 * of a sibling link, e.g. one entered by mistake.
 */
final class UnlinkSiblingsAction extends Action
{
    public function execute(UnlinkSiblingsData $data): void
    {
        $student = Student::findOrFail($data->studentId);
        $sibling = Student::findOrFail($data->siblingStudentId);

        $this->transaction(function () use ($student, $sibling): void {
            StudentSibling::query()->where(fn ($q) => $q->where('student_id', $student->id)->where('sibling_student_id', $sibling->id))
                ->orWhere(fn ($q) => $q->where('student_id', $sibling->id)->where('sibling_student_id', $student->id))->delete();

            event(new SiblingsLinked($student, $sibling, false));
        });
    }
}
