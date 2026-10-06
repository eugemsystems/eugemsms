<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\ChangeStudentStatusData;
use Modules\People\Domain\DataObjects\RecordStudentTimelineEventData;
use Modules\People\Domain\DataObjects\TransferOutStudentData;
use Modules\People\Domain\Events\LearnerWithdrawn;
use Modules\People\Domain\Exceptions\LearnerClearanceIncompleteException;
use Modules\People\Models\Student;

/**
 * ACT-TransferOut (Book C PPL-01 §5/BR-PPL-01-014). Clearance check, then the
 * status change to `transferred`. Every failed clearance is listed; the head
 * may transfer anyway only by giving a reason, which is kept on the learner's
 * timeline. `LearnerWithdrawn` fires with the exit date so the pro-rata credit
 * is raised exactly as for a withdrawal.
 */
final class TransferOutStudentAction extends Action
{
    public function __construct(
        private readonly CheckLearnerClearanceAction $checkClearance,
        private readonly ChangeStudentStatusAction $changeStatus,
        private readonly RecordStudentTimelineEventAction $recordTimeline,
    ) {}

    public function execute(TransferOutStudentData $data): Student
    {
        $student = Student::findOrFail($data->studentId);

        if ($data->exitedOn->isFuture() || $data->exitedOn->lt($student->created_at?->startOfDay() ?? Carbon::create(2000))) {
            throw new InvalidArgumentException('The exit date cannot be in the future or before the learner joined.');
        }

        $reasons = collect($this->checkClearance->execute($student->id))->flatten()->values()->all();
        $override = $data->clearanceOverrideReason === null ? null : trim($data->clearanceOverrideReason);

        if ($reasons !== [] && ($override === null || mb_strlen($override) < 10)) {
            throw LearnerClearanceIncompleteException::forLearner($student->id, $reasons);
        }

        return $this->transaction(function () use ($student, $data, $reasons, $override): Student {
            $updated = $this->changeStatus->execute(new ChangeStudentStatusData(
                studentId: $student->id,
                newStatus: 'transferred',
                changedByUserId: $data->transferredByUserId,
                reasonCode: 'transfer_out',
                reason: $data->reason,
            ));

            $updated->update(['exited_on' => $data->exitedOn->toDateString(), 'updated_by' => $data->transferredByUserId]);

            $this->recordTimeline->execute(new RecordStudentTimelineEventData(
                schoolId: $student->school_id,
                studentId: $student->id,
                eventCategory: 'administrative',
                eventType: 'transferred_out',
                title: 'Transferred out'.($data->destinationSchool !== null ? " to {$data->destinationSchool}" : ''),
                summary: $reasons === [] ? null : "Cleared by override: {$override}",
                severity: $reasons === [] ? 'info' : 'warning',
                occurredAt: $data->exitedOn,
                recordedByUserId: $data->transferredByUserId,
            ));

            event(new LearnerWithdrawn($updated, Carbon::parse($data->exitedOn)));

            return $updated;
        });
    }
}
