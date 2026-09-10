<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\ChangeStudentStatusData;
use Modules\People\Domain\DataObjects\WithdrawStudentData;
use Modules\People\Domain\Events\LearnerWithdrawn;
use Modules\People\Models\Student;

/**
 * ACT-WithdrawStudent (Book C PPL-01 §5/BR-PPL-01-013/AC-PPL-01-007).
 * Transitions to `withdrawn` via `ChangeStudentStatusAction` (so the
 * state machine still guards it), then emits `LearnerWithdrawn` with
 * the exit date — `FIN-02`'s pro-rata credit, once it exists, keys off
 * this event, not off the status column.
 */
final class WithdrawStudentAction extends Action
{
    public function __construct(
        private readonly ChangeStudentStatusAction $changeStatus,
    ) {}

    public function execute(WithdrawStudentData $data): Student
    {
        return $this->transaction(function () use ($data): Student {
            $student = $this->changeStatus->execute(new ChangeStudentStatusData(
                studentId: $data->studentId,
                newStatus: 'withdrawn',
                changedByUserId: $data->withdrawnByUserId,
                reasonCode: 'withdrawal',
                reason: $data->reason,
            ));

            $student->update(['exited_on' => $data->exitedOn->toDateString(), 'updated_by' => $data->withdrawnByUserId]);

            event(new LearnerWithdrawn($student, Carbon::parse($data->exitedOn)));

            return $student;
        });
    }
}
