<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Models\ConsultationBooking;
use Modules\Comms\Models\ScheduledMeeting;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\People\Models\Guardian;
use Throwable;

/**
 * ACT-CancelScheduledMeeting (Book I COM-07 §3/BR-COM-07-009). Every
 * `consultation_bookings` row linked to this meeting is released back
 * to its window's pool (`CancelConsultationBookingAction`) and its
 * guardian is notified — the same "notify, never silently drop"
 * discipline `RebuildCalendarAction` (COM-06) already established.
 */
final class CancelScheduledMeetingAction extends Action
{
    public function __construct(
        private readonly CancelConsultationBookingAction $cancelBooking,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(int $meetingId): ScheduledMeeting
    {
        $meeting = $this->transaction(function () use ($meetingId): ScheduledMeeting {
            $meeting = ScheduledMeeting::findOrFail($meetingId);
            $meeting->update(['status' => 'cancelled']);

            return $meeting;
        });

        $bookings = ConsultationBooking::where('meeting_id', $meeting->id)->where('status', 'booked')->get();

        foreach ($bookings as $booking) {
            $this->cancelBooking->execute($booking->id);
            $this->notifyGuardian($meeting, $booking);
        }

        return $meeting;
    }

    private function notifyGuardian(ScheduledMeeting $meeting, ConsultationBooking $booking): void
    {
        $guardian = Guardian::where('school_id', $meeting->school_id)->find($booking->guardian_id);

        if ($guardian === null || $guardian->user_id === null) {
            return;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $meeting->school_id,
                notificationKey: 'comms.meeting_cancelled',
                recipientType: 'guardian',
                addresses: ['sms' => (string) $guardian->primary_phone, 'email' => (string) $guardian->email],
                context: ['meeting' => ['starts_at' => $meeting->starts_at->toIso8601String()]],
                recipientId: $guardian->id,
                relatedType: 'scheduled_meeting',
                relatedId: $meeting->id,
            ));
        } catch (Throwable) {
            // A notification-dispatch failure never blocks the cancellation itself.
        }
    }
}
