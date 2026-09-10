<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

/**
 * Book I COM-07 §3 ⭐/BR-COM-07-007/008 (AC-COM-07-002/003). One raw
 * `meeting_attendance` row's advisory read — never itself a write to
 * `ACA-04`. `studentId === null` means unmatched: "surfaced to the
 * teacher for manual reconciliation" rather than silently dropped.
 */
final readonly class AttendanceReconciliationSuggestion
{
    public function __construct(
        public int $meetingAttendanceId,
        public string $participantIdentifier,
        public ?int $studentId,
        public ?int $durationSeconds,
        public ?int $attendedPercent,
        public bool $belowThreshold,
        public string $note,
    ) {}
}
