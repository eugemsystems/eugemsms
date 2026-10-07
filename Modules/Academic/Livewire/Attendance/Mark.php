<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Attendance;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\AmendAttendanceRecordAction;
use Modules\Academic\Domain\Actions\GenerateAttendanceSessionAction;
use Modules\Academic\Domain\Actions\MarkAttendanceAction;
use Modules\Academic\Domain\DataObjects\AmendAttendanceRecordData;
use Modules\Academic\Domain\DataObjects\GenerateAttendanceSessionData;
use Modules\Academic\Domain\DataObjects\MarkAttendanceData;
use Modules\Academic\Domain\DataObjects\MarkAttendanceRecordInput;
use Modules\Academic\Models\AttendanceReasonCode;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\AttendanceSession;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\TeachingGroupMember;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\People\Models\Student;

/**
 * `Attendance\Mark` (Book D ACA-04 §3/§4 ⭐, `academic.attendance.mark`
 * to mark, `.amend`/`.amend_locked` to amend an existing record). Two
 * modes: `daily` (one screen per class per day, finds-or-generates its
 * own session via `GenerateAttendanceSessionAction`) and `period`
 * (built 2026-10-07 — picks from the period-mode sessions
 * `GenerateAttendanceSessionsFromTimetableAction` already generated
 * from the published timetable for the chosen date; this screen never
 * generates a period/subject session itself, only daily ones, since
 * period sessions are timetable-driven by design). A period session's
 * roster comes from `TeachingGroupMember` when it carries a teaching
 * group, or `ClassAllocation` otherwise — the same split
 * `GenerateAttendanceSessionAction`'s own `expectedCount()` already
 * makes. Also hosts amendment inline (status change + mandatory
 * reason) rather than a separate route, since a mark worth amending is
 * always one already visible on this same roster.
 */
#[Title('Mark register')]
#[Layout('layouts.app')]
final class Mark extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $mode = 'daily';

    public ?int $classId = null;

    public ?int $sessionId = null;

    public string $sessionDate = '';

    /** @var array<int, string> */
    public array $statuses = [];

    /** @var array<int, int|null> */
    public array $reasonCodeIds = [];

    public ?int $amendingStudentId = null;

    public string $amendmentReason = '';

    public bool $overrideLock = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.attendance.mark');

        $this->sessionDate = now()->toDateString();
    }

    public function updatedMode(): void
    {
        $this->classId = null;
        $this->sessionId = null;
        $this->statuses = [];
        $this->reasonCodeIds = [];
    }

    public function updatedClassId(): void
    {
        $this->statuses = [];
        $this->reasonCodeIds = [];
    }

    public function updatedSessionId(): void
    {
        $this->statuses = [];
        $this->reasonCodeIds = [];
    }

    public function updatedSessionDate(): void
    {
        $this->sessionId = null;
        $this->statuses = [];
        $this->reasonCodeIds = [];
    }

    public function markAllPresent(): void
    {
        foreach ($this->roster() as $student) {
            $this->statuses[$student->id] = 'present';
        }
    }

    public function save(): void
    {
        $session = $this->currentSession();

        if ($session === null) {
            $this->toast($this->mode === 'period' ? __('Select a session first.') : __('Select a class first.'), 'danger');

            return;
        }

        $records = [];

        foreach ($this->roster() as $student) {
            $status = $this->statuses[$student->id] ?? null;

            if ($status === null) {
                continue;
            }

            $records[] = new MarkAttendanceRecordInput(
                studentId: $student->id,
                status: $status,
                reasonCodeId: $this->reasonCodeIds[$student->id] ?? null,
            );
        }

        if ($records === []) {
            $this->toast(__('Mark at least one learner before saving.'), 'danger');

            return;
        }

        $result = app(MarkAttendanceAction::class)->execute(new MarkAttendanceData(
            sessionId: $session->id,
            records: $records,
            markedByUserId: (int) Auth::id(),
        ));

        if ($result->conflicts !== []) {
            $this->toast(__(':count mark(s) conflicted with an existing entry and were not overwritten.', ['count' => count($result->conflicts)]), 'warning');
        } else {
            $this->toast(__('Register saved.'));
        }
    }

    public function amend(int $studentId): void
    {
        $this->authorizePermission('academic.attendance.amend');

        $status = $this->statuses[$studentId] ?? null;

        if ($status === null || $this->amendmentReason === '') {
            $this->toast(__('Pick a new status and give a reason before amending.'), 'danger');

            return;
        }

        $record = AttendanceRecord::query()
            ->where('session_id', $this->currentSession()?->id)
            ->where('student_id', $studentId)
            ->first();

        if ($record === null) {
            $this->toast(__('No existing record to amend for this learner.'), 'danger');

            return;
        }

        if ($this->overrideLock) {
            $this->authorizePermission('academic.attendance.amend_locked');
        }

        try {
            app(AmendAttendanceRecordAction::class)->execute(new AmendAttendanceRecordData(
                recordId: $record->id,
                newStatus: $status,
                amendedByUserId: (int) Auth::id(),
                amendmentReason: $this->amendmentReason,
                reasonCodeId: $this->reasonCodeIds[$studentId] ?? null,
                overrideLock: $this->overrideLock,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['amendingStudentId', 'amendmentReason', 'overrideLock']);
        $this->toast(__('Record amended.'));
    }

    /**
     * @return Collection<int, Student>
     */
    private function roster(): Collection
    {
        if ($this->mode === 'period') {
            return $this->rosterForSession($this->currentSession());
        }

        if ($this->classId === null) {
            return collect();
        }

        return ClassAllocation::query()
            ->where('class_id', $this->classId)
            ->where('status', 'confirmed')
            ->where('effective_from', '<=', $this->sessionDate)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $this->sessionDate))
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, Student>
     */
    private function rosterForSession(?AttendanceSession $session): Collection
    {
        if ($session === null) {
            return collect();
        }

        if ($session->teaching_group_id !== null) {
            return TeachingGroupMember::query()
                ->where('teaching_group_id', $session->teaching_group_id)
                ->where('effective_from', '<=', $session->session_date)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $session->session_date))
                ->with('student')
                ->get()
                ->pluck('student')
                ->filter()
                ->values();
        }

        if ($session->class_id !== null) {
            return ClassAllocation::query()
                ->where('class_id', $session->class_id)
                ->where('status', 'confirmed')
                ->where('effective_from', '<=', $session->session_date)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $session->session_date))
                ->with('student')
                ->get()
                ->pluck('student')
                ->filter()
                ->values();
        }

        return collect();
    }

    private function currentSession(): ?AttendanceSession
    {
        if ($this->mode === 'period') {
            return $this->sessionId === null
                ? null
                : AttendanceSession::where('school_id', $this->school->id)->where('mode', 'period')->find($this->sessionId);
        }

        if ($this->classId === null) {
            return null;
        }

        $termId = SessionContext::termId();
        $yearId = SessionContext::yearId();

        if ($termId === null || $yearId === null) {
            return null;
        }

        $existing = AttendanceSession::query()
            ->where('school_id', $this->school->id)
            ->whereDate('session_date', $this->sessionDate)
            ->where('mode', 'daily')
            ->where('class_id', $this->classId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return app(GenerateAttendanceSessionAction::class)->execute(new GenerateAttendanceSessionData(
            schoolId: $this->school->id,
            academicYearId: $yearId,
            termId: $termId,
            sessionDate: Carbon::parse($this->sessionDate),
            mode: 'daily',
            classId: $this->classId,
        ));
    }

    /**
     * @return Collection<int, AttendanceSession>
     */
    private function periodSessions(): Collection
    {
        if ($this->sessionDate === '') {
            return collect();
        }

        return AttendanceSession::query()
            ->where('school_id', $this->school->id)
            ->whereDate('session_date', $this->sessionDate)
            ->where('mode', 'period')
            ->with(['subject', 'schoolClass', 'teachingGroup'])
            ->orderBy('period_number')
            ->get();
    }

    public function render(): View
    {
        $session = $this->currentSession();

        $existingRecords = $session !== null
            ? AttendanceRecord::where('session_id', $session->id)->get()->keyBy('student_id')
            : collect();

        return view('academic::attendance.mark', [
            'classes' => SchoolClass::where('school_id', $this->school->id)->where('is_active', true)->orderBy('name')->get(),
            'periodSessions' => $this->mode === 'period' ? $this->periodSessions() : collect(),
            'roster' => $this->roster(),
            'session' => $session,
            'existingRecords' => $existingRecords,
            'reasonCodes' => AttendanceReasonCode::where('school_id', $this->school->id)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
