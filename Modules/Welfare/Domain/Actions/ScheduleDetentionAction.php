<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Sport\Models\Fixture;
use Modules\Welfare\Domain\DataObjects\ScheduleDetentionData;
use Modules\Welfare\Domain\Events\DetentionScheduled;
use Modules\Welfare\Domain\Exceptions\DetentionFixtureClashException;
use Modules\Welfare\Models\Detention;

/**
 * ACT-ScheduleDetention (Book G BRD-07 §2/BR-BRD-07-011 ⭐). Closes the
 * `detention` roll-status stub `BRD-02` §4 left open —
 * `OpenRollCallAction` queries `detentions.status` directly. **Built
 * 2026-10-07**: the sports-fixture clash check (AC-BRD-07-009) — a
 * same-day check only (the learner is named in an unplayed `Fixture`'s
 * `squad_student_ids` on `scheduledDate`), not a fine-grained time
 * overlap, since a home fixture carries no end-time column to compare
 * against; the AC's own wording ("scheduled during a sports fixture")
 * is itself day-level, not minute-level.
 */
final class ScheduleDetentionAction extends Action
{
    public function execute(ScheduleDetentionData $data): Detention
    {
        $clash = Fixture::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->whereDate('fixture_date', $data->scheduledDate->toDateString())
            ->where('status', '!=', 'completed')
            ->get()
            ->first(fn (Fixture $fixture): bool => in_array($data->studentId, $fixture->squad_student_ids ?? [], true));

        if ($clash !== null) {
            throw DetentionFixtureClashException::forFixture($data->studentId, $clash->id, $clash->opponent);
        }

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
