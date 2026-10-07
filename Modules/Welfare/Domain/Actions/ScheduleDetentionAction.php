<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\ScheduleDetentionData;
use Modules\Welfare\Domain\Events\DetentionScheduled;
use Modules\Welfare\Models\Detention;

/**
 * ACT-ScheduleDetention (Book G BRD-07 §2/BR-BRD-07-011 ⭐). Closes the
 * `detention` roll-status stub `BRD-02` §4 left open —
 * `OpenRollCallAction` queries `detentions.status` directly. A clash
 * check against a sports fixture (AC-BRD-07-009) is deliberately not
 * implemented: `OPS-07` (Book H2) has since built `Modules\Sport\Models\Fixture`,
 * so the table this would query now exists, but no cross-module query
 * against it has been added here yet — a genuine, still-open gap, just
 * no longer blocked on the table not existing.
 */
final class ScheduleDetentionAction extends Action
{
    public function execute(ScheduleDetentionData $data): Detention
    {
        return $this->transaction(function () use ($data): Detention {
            $detention = Detention::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'sanction_id' => $data->sanctionId,
                'student_id' => $data->studentId,
                'scheduled_date' => $data->scheduledDate->toDateString(),
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'venue' => $data->venue,
                'supervisor_staff_id' => $data->supervisorStaffId,
                'task_set' => $data->taskSet,
                'status' => 'scheduled',
            ]);

            event(new DetentionScheduled($detention));

            return $detention;
        });
    }
}
