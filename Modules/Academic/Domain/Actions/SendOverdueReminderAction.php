<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\SendOverdueReminderData;
use Modules\Academic\Models\Loan;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\People\Models\Student;
use Throwable;

/**
 * ACT-SendOverdueReminder (Book K ACA-10 §4/BR-ACA-10-004). Fires
 * through the real `DispatchNotificationAction` (Book A CORE-09)
 * before any fine is charged — never a second notification path. Only
 * a student borrower with a linked user account can be reached
 * in-app; a dispatch failure never blocks anything else.
 */
final class SendOverdueReminderAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(SendOverdueReminderData $data): bool
    {
        $loan = Loan::with(['copy.item'])->findOrFail($data->loanId);

        if ($loan->borrower_type !== 'student') {
            return false;
        }

        $student = Student::find($loan->borrower_id);

        if ($student === null || $student->user_id === null) {
            return false;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $loan->school_id,
                notificationKey: 'library.overdue_reminder',
                recipientType: 'student',
                addresses: ['in_app' => (string) $student->user_id],
                context: [
                    'item' => ['title' => $loan->copy->item->title],
                    'loan' => ['due_on' => $loan->due_on->toDateString()],
                ],
                recipientId: $student->user_id,
                channel: 'in_app',
                relatedType: 'library_overdue_loan',
                relatedId: $loan->id,
                dedupeWindowMinutes: 1440,
            ));

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
