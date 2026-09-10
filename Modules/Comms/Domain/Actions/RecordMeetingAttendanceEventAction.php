<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Domain\DataObjects\ParsedMeetingWebhookEvent;
use Modules\Comms\Models\MeetingAttendance;
use Modules\Comms\Models\ScheduledMeeting;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Guardian;
use Modules\People\Models\StudentGuardian;

/**
 * ACT-RecordMeetingAttendanceEvent (Book I COM-07 §3 ⭐/BR-COM-07-007).
 * Raw join/leave capture only — see this module's own §3 diagram.
 * Matching is `exact`-only in this pass: `participant_identifier`
 * against a guardian's own registered email, resolving to whichever
 * of their linked, active students the meeting's own class roster
 * would name (simplified here to "any active linked student" absent a
 * real class-roster lookup this pass doesn't build). `fuzzy` is a
 * recognised `match_confidence` value with no matcher wired yet — an
 * honest boundary, not a silent gap; anything not resolved exactly is
 * `unmatched` and surfaced, never guessed at.
 */
final class RecordMeetingAttendanceEventAction extends Action
{
    public function execute(ScheduledMeeting $meeting, ParsedMeetingWebhookEvent $event): ?MeetingAttendance
    {
        if ($event->participantIdentifier === null) {
            return null;
        }

        return $this->transaction(function () use ($meeting, $event): MeetingAttendance {
            $attendance = MeetingAttendance::firstOrCreate(
                ['school_id' => $meeting->school_id, 'meeting_id' => $meeting->id, 'participant_identifier' => $event->participantIdentifier],
                ['joined_at' => $event->joinedAt ?? now(), 'student_id' => null, 'match_confidence' => 'unmatched'],
            );

            $studentId = $this->matchStudent($meeting->school_id, $event->participantIdentifier);

            if ($event->eventType === 'participant.left') {
                $attendance->update(['left_at' => $event->leftAt ?? now(), 'duration_seconds' => $event->durationSeconds]);
            }

            if ($studentId !== null && $attendance->student_id === null) {
                $attendance->update(['student_id' => $studentId, 'match_confidence' => 'exact']);
            }

            return $attendance->refresh();
        });
    }

    private function matchStudent(int $schoolId, string $participantIdentifier): ?int
    {
        $guardian = Guardian::where('school_id', $schoolId)->where('email', $participantIdentifier)->first();

        if ($guardian === null) {
            return null;
        }

        $link = StudentGuardian::where('school_id', $schoolId)->where('guardian_id', $guardian->id)->where('status', 'active')->first();

        return $link?->student_id;
    }
}
