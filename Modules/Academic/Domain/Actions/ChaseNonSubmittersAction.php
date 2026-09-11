<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\ChaseNonSubmittersData;
use Modules\Academic\Models\Assignment;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\People\Models\Student;
use Throwable;

/**
 * ACT-ChaseNonSubmitters (Book K ACA-08 §4/BR-ACA-08-009). The
 * teacher's one-tap reminder, through the real `DispatchNotificationAction`
 * (Book A CORE-09) — never a second notification path. A student with
 * no linked user account is silently skipped (no in-app inbox to
 * deliver into), and a dispatch failure never blocks the others — same
 * "messaging never blocks the operation it attaches to" rule as
 * `MarkAttendanceAction`.
 */
final class ChaseNonSubmittersAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    /**
     * @return int number of reminders actually dispatched
     */
    public function execute(ChaseNonSubmittersData $data): int
    {
        $assignment = Assignment::findOrFail($data->assignmentId);
        $sent = 0;

        foreach (Student::query()->whereIn('id', $data->studentIds)->get() as $student) {
            if ($student->user_id === null) {
                continue;
            }

            try {
                $this->dispatchNotification->execute(new DispatchNotificationData(
                    schoolId: $assignment->school_id,
                    notificationKey: 'lms.non_submission_reminder',
                    recipientType: 'student',
                    addresses: ['in_app' => (string) $student->user_id],
                    context: [
                        'assignment' => ['title' => $assignment->title, 'due_at' => $assignment->due_at->toDateString()],
                    ],
                    recipientId: $student->user_id,
                    channel: 'in_app',
                    relatedType: 'lms_assignment_reminder',
                    relatedId: $assignment->id,
                    dedupeWindowMinutes: 1440,
                ));

                $sent++;
            } catch (Throwable) {
                // BR-ACA-08-009's chase never blocks the others.
            }
        }

        return $sent;
    }
}
