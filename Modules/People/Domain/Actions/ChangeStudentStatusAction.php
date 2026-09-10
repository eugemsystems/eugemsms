<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\ChangeStudentStatusData;
use Modules\People\Domain\Events\LearnerStatusChanged;
use Modules\People\Domain\Support\StudentStatusMachine;
use Modules\People\Models\Student;

/**
 * ACT-ChangeStudentStatus (Book C PPL-01 §6/BR-PPL-01-011/012). The
 * single gateway for `status`. Suspension deliberately does **not**
 * stop billing (BR-PPL-01-012) — this action only moves the state
 * machine; whether to waive fees during a suspension is a separate,
 * approved `FIN-03` decision.
 */
final class ChangeStudentStatusAction extends Action
{
    public function execute(ChangeStudentStatusData $data): Student
    {
        $student = Student::findOrFail($data->studentId);

        if (! StudentStatusMachine::canTransition($student->status, $data->newStatus)) {
            throw new InvalidStateTransitionException(
                "Cannot transition a student from [{$student->status}] to [{$data->newStatus}].",
                ['student_id' => $student->id, 'from' => $student->status, 'to' => $data->newStatus],
            );
        }

        return $this->transaction(function () use ($student, $data): Student {
            $fromStatus = $student->status;

            $student->statusChangeAuthorized = true;
            $student->update([
                'status' => $data->newStatus,
                'status_reason_code' => $data->reasonCode,
                'status_changed_at' => Carbon::now(),
                'status_changed_by' => $data->changedByUserId,
                'updated_by' => $data->changedByUserId,
            ]);

            event(new LearnerStatusChanged($student, $fromStatus, $data->newStatus));

            return $student;
        });
    }
}
