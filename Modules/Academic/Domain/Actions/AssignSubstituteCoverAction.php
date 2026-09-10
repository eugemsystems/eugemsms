<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\AssignSubstituteCoverData;
use Modules\Academic\Domain\Events\SubstitutionAssigned;
use Modules\Academic\Models\AttendanceSession;
use Modules\Academic\Models\LessonSubstitution;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TimetableSlot;
use Modules\Academic\Models\Venue;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Models\SchoolClass;
use Modules\People\Models\Staff;
use Throwable;

/**
 * ACT-AssignSubstituteCover (Book E ACA-03 §6/BR-ACA-03-018/019/
 * AC-ACA-03-007). Updates the linked `attendance_session.staff_id`
 * so the covering teacher's app shows the register for the class
 * they are actually teaching — the exact mechanism
 * `GET /timetable/today`'s own docs describe — and notifies them
 * through the real notification bus with the class, venue, subject,
 * and work set, same non-blocking-on-failure discipline as
 * `MarkAttendanceAction`'s guardian alert.
 */
final class AssignSubstituteCoverAction extends Action
{
    public function __construct(private readonly DispatchNotificationAction $dispatchNotification) {}

    public function execute(AssignSubstituteCoverData $data): LessonSubstitution
    {
        $substitution = LessonSubstitution::findOrFail($data->substitutionId);

        $updated = $this->transaction(function () use ($substitution, $data): LessonSubstitution {
            $substitution->update([
                'cover_staff_id' => $data->coverStaffId,
                'status' => 'assigned',
                'work_set' => $data->workSet ?? $substitution->work_set,
                'assigned_by' => $data->assignedByUserId,
            ]);

            AttendanceSession::query()
                ->where('timetable_slot_id', $substitution->timetable_slot_id)
                ->whereDate('session_date', $substitution->substitution_date->toDateString())
                ->update(['staff_id' => $data->coverStaffId]);

            return $substitution->fresh();
        });

        $this->notifyCoverStaff($updated);

        event(new SubstitutionAssigned($updated));

        return $updated->fresh();
    }

    private function notifyCoverStaff(LessonSubstitution $substitution): void
    {
        $staff = Staff::find($substitution->cover_staff_id);

        if ($staff?->user_id === null) {
            return;
        }

        $slot = TimetableSlot::find($substitution->timetable_slot_id);

        if ($slot === null) {
            return;
        }

        $subjectName = '';
        $subject = Subject::find($slot->subject_id);

        if ($subject !== null) {
            $subjectName = $subject->name;
        }

        $className = '';

        if ($slot->class_id !== null) {
            $schoolClass = SchoolClass::find($slot->class_id);

            if ($schoolClass !== null) {
                $className = $schoolClass->name;
            }
        }

        $venueName = '';

        if ($slot->venue_id !== null) {
            $venue = Venue::find($slot->venue_id);

            if ($venue !== null) {
                $venueName = $venue->name;
            }
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $substitution->school_id,
                notificationKey: 'timetable.cover_assigned',
                recipientType: 'staff',
                addresses: ['email' => (string) ($staff->work_email ?? $staff->personal_email)],
                context: [
                    'class' => ['name' => $className],
                    'subject' => ['name' => $subjectName],
                    'venue' => ['name' => $venueName],
                    'work_set' => $substitution->work_set ?? '',
                ],
                recipientId: $staff->user_id,
                relatedType: 'lesson_substitution',
                relatedId: $substitution->id,
            ));

            $substitution->update(['notified_at' => Carbon::now()]);
        } catch (Throwable) {
            // BR-ACA-03-019's notification never blocks the assignment it attaches to.
        }
    }
}
