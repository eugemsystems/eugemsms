<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Models\AttendanceSession;
use Modules\Comms\Domain\DataObjects\AttendanceReconciliationSuggestion;
use Modules\Comms\Domain\Exceptions\AttendanceSessionNotFoundForMeetingException;
use Modules\Comms\Models\MeetingAttendance;
use Modules\Comms\Models\ScheduledMeeting;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;

/**
 * ACT-PreviewAttendanceReconciliation (Book I COM-07 §3 ⭐/BR-COM-07-007/008
 * (AC-COM-07-002/003)). Read-only — this NEVER writes to `ACA-04`,
 * matching `BR-COM-07-007`'s "advisory" wording literally. Every raw
 * `meeting_attendance` row becomes one suggestion; an unmatched
 * participant is surfaced with `studentId = null` rather than dropped
 * (the diagram's "surfaced to the teacher for manual reconciliation").
 * A teacher reviewing these decides the REAL status through
 * `ConfirmAttendanceReconciliationAction` — this action never proposes
 * a fifth attendance status ACA-04 doesn't have; "present-but-flagged"
 * is carried as `belowThreshold`/`note` context for the teacher to act
 * on with ACA-04's own real status vocabulary.
 */
final class PreviewAttendanceReconciliationAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return array{session: AttendanceSession, suggestions: array<int, AttendanceReconciliationSuggestion>}
     */
    public function execute(int $meetingId): array
    {
        $meeting = ScheduledMeeting::findOrFail($meetingId);

        if ($meeting->timetable_slot_id === null) {
            throw new InvalidArgumentException("Meeting #{$meetingId} is not linked to a timetable slot — attendance reconciliation only applies to an online_lesson meeting.");
        }

        $session = AttendanceSession::where('school_id', $meeting->school_id)
            ->where('timetable_slot_id', $meeting->timetable_slot_id)
            ->first();

        if ($session === null) {
            throw AttendanceSessionNotFoundForMeetingException::forMeeting($meetingId, $meeting->timetable_slot_id);
        }

        $threshold = (int) $this->settings->get('attendance.online_minimum_attendance_percent', new ScopeChain(schoolId: $meeting->school_id));
        $lessonSeconds = $meeting->duration_minutes * 60;

        $suggestions = MeetingAttendance::where('meeting_id', $meeting->id)
            ->get()
            ->map(function (MeetingAttendance $attendance) use ($threshold, $lessonSeconds): AttendanceReconciliationSuggestion {
                $percent = $attendance->duration_seconds !== null && $lessonSeconds > 0
                    ? (int) round(min(100, $attendance->duration_seconds / $lessonSeconds * 100))
                    : null;
                $belowThreshold = $percent !== null && $percent < $threshold;

                $note = $attendance->student_id === null
                    ? "Unmatched participant '{$attendance->participant_identifier}' — needs manual reconciliation."
                    : ($percent !== null
                        ? "Video conference: present for {$percent}% of the lesson".($belowThreshold ? " — below the {$threshold}% threshold." : '.')
                        : 'Video conference: still in progress or duration not yet recorded.');

                return new AttendanceReconciliationSuggestion(
                    meetingAttendanceId: $attendance->id,
                    participantIdentifier: $attendance->participant_identifier,
                    studentId: $attendance->student_id,
                    durationSeconds: $attendance->duration_seconds,
                    attendedPercent: $percent,
                    belowThreshold: $belowThreshold,
                    note: $note,
                );
            })
            ->values()
            ->all();

        return ['session' => $session, 'suggestions' => $suggestions];
    }
}
