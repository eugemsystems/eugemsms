<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\RecordMarkingComplianceData;
use Modules\Academic\Domain\Events\RegisterNotMarked;
use Modules\Academic\Models\AttendanceMarkingCompliance;
use Modules\Academic\Models\AttendanceSession;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Staff;
use Modules\People\Models\TeacherAllocation;

/**
 * ACT-RecordMarkingCompliance (Book D ACA-04 §2/§4/BR-ACA-04-014). A
 * session is "this staff's to mark" when `PPL-04`'s own
 * `TeacherAllocation` says so for that date — class-teacher allocations
 * cover form-class/daily sessions, subject allocations cover
 * subject/period sessions on the matching class. No second allocation
 * concept is introduced here (`ACA-03` timetabling, once built, is
 * expected to narrow this further, not replace it).
 */
final class RecordMarkingComplianceAction extends Action
{
    public function __construct(private readonly SettingResolver $settings) {}

    public function execute(RecordMarkingComplianceData $data): AttendanceMarkingCompliance
    {
        $staff = Staff::findOrFail($data->staffId);
        $on = $data->sessionDate->toDateString();

        $allocations = TeacherAllocation::query()
            ->where('staff_id', $staff->id)
            ->where('term_id', $data->termId)
            ->where('status', 'active')
            ->where('starts_on', '<=', $on)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $on))
            ->get(['class_id', 'subject_id', 'is_class_teacher']);

        $classTeacherOf = $allocations->where('is_class_teacher', true)->pluck('class_id')->all();
        $subjectPairs = $allocations->map(fn (TeacherAllocation $a): string => "{$a->class_id}:{$a->subject_id}")->all();

        $sessions = AttendanceSession::query()
            ->where('term_id', $data->termId)
            ->whereDate('session_date', $on)
            ->get()
            ->filter(function (AttendanceSession $session) use ($classTeacherOf, $subjectPairs): bool {
                if ($session->class_id !== null && in_array($session->class_id, $classTeacherOf, true) && $session->subject_id === null) {
                    return true;
                }

                return $session->class_id !== null && $session->subject_id !== null
                    && in_array("{$session->class_id}:{$session->subject_id}", $subjectPairs, true);
            });

        $expected = $sessions->count();
        $marked = $sessions->whereIn('status', ['partial', 'completed'])->count();
        $markedLate = $sessions->filter(fn (AttendanceSession $s): bool => $s->marked_at !== null && $this->isLate($s, $data->termId))->count();
        $percent = $expected > 0 ? round(($marked / $expected) * 100, 2) : null;

        // updateOrCreate's own match array does a bare `where()`, which
        // would never find an existing row against a 'date'-cast column
        // (stored as 'Y-m-d 00:00:00' — see the module's own recorded
        // rule on this) — resolved explicitly via whereDate() first.
        $compliance = AttendanceMarkingCompliance::query()
            ->where('school_id', $staff->school_id)
            ->where('staff_id', $staff->id)
            ->whereDate('session_date', $on)
            ->first() ?? new AttendanceMarkingCompliance([
                'school_id' => $staff->school_id, 'staff_id' => $staff->id, 'session_date' => $on,
            ]);

        $compliance->fill([
            'term_id' => $data->termId,
            'expected_sessions' => $expected,
            'marked_sessions' => $marked,
            'marked_late_sessions' => $markedLate,
            'compliance_percent' => $percent,
        ])->save();

        foreach ($sessions->where('status', 'pending') as $session) {
            event(new RegisterNotMarked($session));
        }

        return $compliance;
    }

    private function isLate(AttendanceSession $session, int $termId): bool
    {
        if ($session->mode !== 'daily' || $session->marked_at === null) {
            return false;
        }

        $cutoff = (string) $this->settings->get('attendance.registration_cutoff_time', new ScopeChain(schoolId: $session->school_id, termId: $termId));

        if ($cutoff === '') {
            return false;
        }

        return $session->marked_at->format('H:i') > $cutoff;
    }
}
