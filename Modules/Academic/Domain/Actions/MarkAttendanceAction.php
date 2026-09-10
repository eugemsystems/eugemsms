<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\MarkAttendanceData;
use Modules\Academic\Domain\DataObjects\MarkAttendanceResult;
use Modules\Academic\Domain\Events\AttendanceMarked;
use Modules\Academic\Domain\Events\LearnerAbsentUnexplained;
use Modules\Academic\Models\AttendanceReasonCode;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\AttendanceSession;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Throwable;

/**
 * ACT-MarkAttendance (Book D ACA-04 §3/§4/BR-ACA-04-008/017/018).
 * Bulk-safe by design (BR-ACA-04-017): every learner in `$data->records`
 * gets its own row, one write each, so a later amendment is always
 * per-learner. Idempotent per (session, student): a repeat submission
 * matching the stored `idempotency_key` OR carrying the same `status`
 * is a harmless replay; a genuinely conflicting resubmission is
 * reported back in `MarkAttendanceResult::$conflicts` and the first
 * mark is left untouched — never silently overwritten, never thrown
 * away either (AC-ACA-04-005).
 *
 * `attendance_sessions.status` is only ever `pending`/`partial`/
 * `completed` here, derived strictly from how many of
 * `expected_count` are actually marked — BR-ACA-04-018 forbids ever
 * inferring "everyone present" from an unmarked register.
 *
 * The BR-ACA-04-006 guardian notification dispatches through the real
 * `DispatchNotificationAction` (Book A CORE-09) — the first production
 * caller of that bus anywhere in the codebase — strictly after the
 * marking transaction commits, and a dispatch failure is swallowed
 * (messaging never blocks the operation it attaches to). "Primary
 * contact" is `student_guardian.is_primary_contact`; a learner with no
 * active primary contact is silently skipped rather than guessed at.
 */
final class MarkAttendanceAction extends Action
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(MarkAttendanceData $data): MarkAttendanceResult
    {
        $session = AttendanceSession::findOrFail($data->sessionId);
        $conflicts = [];
        $newlyUnexplained = [];

        $this->transaction(function () use ($session, $data, &$conflicts, &$newlyUnexplained): void {
            foreach ($data->records as $input) {
                $existing = AttendanceRecord::query()
                    ->where('session_id', $session->id)
                    ->where('student_id', $input->studentId)
                    ->first();

                if ($existing !== null) {
                    $sameKey = $input->idempotencyKey !== null && $existing->idempotency_key === $input->idempotencyKey;
                    $sameStatus = $existing->status === $input->status;

                    if (! $sameKey && ! $sameStatus) {
                        $conflicts[] = [
                            'student_id' => $input->studentId,
                            'existing_status' => $existing->status,
                            'attempted_status' => $input->status,
                        ];
                    }

                    continue;
                }

                $record = AttendanceRecord::create([
                    'school_id' => $session->school_id,
                    'session_id' => $session->id,
                    'student_id' => $input->studentId,
                    'term_id' => $session->term_id,
                    'session_date' => $session->session_date,
                    'status' => $input->status,
                    'reason_code_id' => $input->reasonCodeId,
                    'minutes_late' => $input->minutesLate,
                    'note' => $input->note,
                    'idempotency_key' => $input->idempotencyKey,
                    'marked_by' => $data->markedByUserId,
                    'marked_at' => Carbon::now(),
                ]);

                if ($this->isUnexplainedAbsence($record)) {
                    $newlyUnexplained[] = $record;
                }
            }

            $this->recalculateCounts($session);

            $session->update(['marked_by' => $data->markedByUserId, 'marked_at' => Carbon::now()]);
        });

        event(new AttendanceMarked($session->fresh()));

        foreach ($newlyUnexplained as $record) {
            event(new LearnerAbsentUnexplained($record));
            $this->notifyPrimaryContact($record);
        }

        return new MarkAttendanceResult($session->fresh(), $conflicts);
    }

    private function isUnexplainedAbsence(AttendanceRecord $record): bool
    {
        if ($record->status !== 'absent') {
            return false;
        }

        $reason = $record->reason_code_id !== null ? AttendanceReasonCode::find($record->reason_code_id) : null;

        return $reason === null || ! $reason->suppresses_notification;
    }

    private function recalculateCounts(AttendanceSession $session): void
    {
        $counts = AttendanceRecord::query()
            ->where('session_id', $session->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $markedTotal = (int) $counts->sum();

        $session->update([
            'present_count' => (int) ($counts['present'] ?? 0),
            'absent_count' => (int) ($counts['absent'] ?? 0),
            'late_count' => (int) ($counts['late'] ?? 0),
            'excused_count' => (int) ($counts['excused'] ?? 0),
            'status' => match (true) {
                $markedTotal === 0 => 'pending',
                $markedTotal >= $session->expected_count => 'completed',
                default => 'partial',
            },
        ]);
    }

    private function notifyPrimaryContact(AttendanceRecord $record): void
    {
        $link = StudentGuardian::query()
            ->where('student_id', $record->student_id)
            ->where('is_primary_contact', true)
            ->where('status', 'active')
            ->with('guardian')
            ->first();

        if ($link === null || $link->guardian === null) {
            return;
        }

        $student = Student::find($record->student_id);

        if ($student === null) {
            return;
        }

        $guardian = $link->guardian;
        $guardianName = trim("{$guardian->first_name} {$guardian->last_name}") ?: ($guardian->organisation_name ?? 'Guardian');

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $record->school_id,
                notificationKey: 'attendance.unexplained_absence',
                recipientType: 'guardian',
                addresses: [
                    'sms' => (string) $guardian->primary_phone,
                    'email' => (string) $guardian->email,
                ],
                context: [
                    'guardian' => ['name' => $guardianName],
                    'student' => ['first_name' => $student->first_name, 'last_name' => $student->last_name],
                    'date' => $record->session_date->toDateString(),
                ],
                recipientId: $guardian->id,
                relatedType: 'attendance_daily_absence',
                relatedId: $record->student_id,
                dedupeWindowMinutes: 1440,
            ));
        } catch (Throwable) {
            // BR-ACA-04-006's notification never blocks the mark it attaches to.
        }
    }
}
