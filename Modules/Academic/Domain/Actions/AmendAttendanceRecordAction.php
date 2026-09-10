<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\AmendAttendanceRecordData;
use Modules\Academic\Domain\Events\AttendanceAmended;
use Modules\Academic\Domain\Exceptions\AttendanceSessionLockedException;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\AttendanceSession;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;

/**
 * ACT-AmendAttendanceRecord (Book D ACA-04 §4/BR-ACA-04-008/009). The
 * only sanctioned way `status` ever changes after creation — the
 * model's own guard refuses a bare `status` update, so this action's
 * job is to always write `original_status` (set once, from whatever
 * `status` held immediately before this amendment — never overwritten
 * on a second amendment) alongside it.
 *
 * `overrideLock` is the caller's proof the actor holds
 * `academic.attendance.amend_locked`, exactly the pattern
 * `AllocateTeacherAction::$overrideCeiling` uses for its own permission
 * gate — this action makes no permission check of its own.
 */
final class AmendAttendanceRecordAction extends Action
{
    public function __construct(private readonly SettingResolver $settings) {}

    public function execute(AmendAttendanceRecordData $data): AttendanceRecord
    {
        $record = AttendanceRecord::findOrFail($data->recordId);
        $session = AttendanceSession::findOrFail($record->session_id);

        $this->assertNotLocked($session, $data->overrideLock);

        return $this->transaction(function () use ($record, $data): AttendanceRecord {
            $record->update([
                'original_status' => $record->original_status ?? $record->status,
                'status' => $data->newStatus,
                'reason_code_id' => $data->reasonCodeId ?? $record->reason_code_id,
                'minutes_late' => $data->minutesLate ?? $record->minutes_late,
                'note' => $data->note ?? $record->note,
                'amended_by' => $data->amendedByUserId,
                'amended_at' => Carbon::now(),
                'amendment_reason' => $data->amendmentReason,
            ]);

            event(new AttendanceAmended($record->fresh()));

            return $record->fresh();
        });
    }

    private function assertNotLocked(AttendanceSession $session, bool $overrideLock): void
    {
        $lockAfterHours = (int) $this->settings->get('attendance.lock_after_hours', new ScopeChain(schoolId: $session->school_id));

        $isLocked = Carbon::now()->greaterThan($session->created_at->copy()->addHours($lockAfterHours));

        if ($isLocked && ! $overrideLock) {
            throw AttendanceSessionLockedException::forSession($session->id, $lockAfterHours);
        }
    }
}
