<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book I COM-07 §3. Per the spec's own diagram, an `ACA-04`
 * `AttendanceSession` for a `timetable_slot_id` is assumed to already
 * exist by the time a meeting's webhooks arrive (created per
 * `ACA-03` §5) — this surfaces the gap honestly rather than silently
 * fabricating a session on COM-07's own authority.
 */
final class AttendanceSessionNotFoundForMeetingException extends DomainException
{
    public static function forMeeting(int $meetingId, int $timetableSlotId): self
    {
        return new self(
            "No ACA-04 attendance session exists yet for timetable slot #{$timetableSlotId} (meeting #{$meetingId}).",
            ['meeting_id' => $meetingId, 'timetable_slot_id' => $timetableSlotId],
        );
    }

    public function errorCode(): string
    {
        return 'ATTENDANCE_SESSION_NOT_FOUND_FOR_MEETING';
    }
}
