<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Meetings;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\ConfirmAttendanceReconciliationAction;
use Modules\Comms\Domain\Actions\PreviewAttendanceReconciliationAction;
use Modules\Comms\Domain\DataObjects\AttendanceReconciliationSuggestion;
use Modules\Comms\Domain\Exceptions\AttendanceSessionNotFoundForMeetingException;
use Modules\Comms\Models\ScheduledMeeting;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Comms\Meetings\AttendanceReview` (Book I COM-07 §5 ⭐,
 * `academic.attendance.mark`). Advisory only (BR-COM-07-007): the
 * preview never writes anything, and the teacher's own chosen status
 * for each *matched* learner is what goes into the real ACA-04 marking
 * flow via `ConfirmAttendanceReconciliationAction`. A learner below
 * the online-attendance threshold is flagged and gets no default
 * status, so the teacher must decide (BR-COM-07-008); an unmatched
 * participant is listed but cannot be marked here — they are marked
 * from the register itself. ACA-04's own idempotency decides conflicts
 * with marks already made; they are reported, never overwritten.
 */
#[Title('Online lesson attendance')]
#[Layout('layouts.app')]
final class AttendanceReview extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $meetingId = null;

    public ?int $sessionId = null;

    /**
     * @var array<int, array{meeting_attendance_id: int, identifier: string, student_id: ?int, percent: ?int, below: bool, note: string}>
     */
    public array $suggestions = [];

    /**
     * @var array<int, string> meeting_attendance_id => chosen status
     */
    public array $decisions = [];

    /**
     * @var array<int, array{student_id: int, existing_status: string, attempted_status: string}>
     */
    public array $conflicts = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.attendance.mark');
    }

    public function updatedMeetingId(): void
    {
        $this->resetErrorBag();
        $this->suggestions = [];
        $this->decisions = [];
        $this->conflicts = [];
        $this->sessionId = null;

        if ($this->meetingId === null) {
            return;
        }

        $meeting = ScheduledMeeting::where('school_id', $this->school->id)->where('meeting_type', 'online_lesson')->findOrFail($this->meetingId);

        try {
            $preview = app(PreviewAttendanceReconciliationAction::class)->execute($meeting->id);
        } catch (AttendanceSessionNotFoundForMeetingException|InvalidArgumentException $exception) {
            $this->addError('meetingId', $exception->getMessage());

            return;
        }

        $this->sessionId = $preview['session']->id;
        $this->suggestions = array_map(fn (AttendanceReconciliationSuggestion $suggestion): array => [
            'meeting_attendance_id' => $suggestion->meetingAttendanceId,
            'identifier' => $suggestion->participantIdentifier,
            'student_id' => $suggestion->studentId,
            'percent' => $suggestion->attendedPercent,
            'below' => $suggestion->belowThreshold,
            'note' => $suggestion->note,
        ], $preview['suggestions']);

        foreach ($this->suggestions as $suggestion) {
            $this->decisions[$suggestion['meeting_attendance_id']] = $suggestion['student_id'] !== null && ! $suggestion['below'] ? 'present' : '';
        }
    }

    public function confirm(): void
    {
        $this->authorizePermission('academic.attendance.mark');

        abort_if($this->meetingId === null, 404);

        $this->resetErrorBag();

        // The session and the matched learners are re-derived server-side from the
        // meeting — public Livewire properties are client-tamperable, so only the
        // statuses the teacher chose are taken from component state.
        $meeting = ScheduledMeeting::where('school_id', $this->school->id)->where('meeting_type', 'online_lesson')->findOrFail($this->meetingId);

        try {
            $preview = app(PreviewAttendanceReconciliationAction::class)->execute($meeting->id);
        } catch (AttendanceSessionNotFoundForMeetingException|InvalidArgumentException $exception) {
            $this->addError('meetingId', $exception->getMessage());

            return;
        }

        $decisions = [];

        foreach ($preview['suggestions'] as $suggestion) {
            $status = $this->decisions[$suggestion->meetingAttendanceId] ?? '';

            if ($suggestion->studentId === null || $status === '') {
                continue;
            }

            if (! in_array($status, ['present', 'late', 'absent', 'excused'], true)) {
                $this->addError('decisions', __('Choose a valid status for each learner.'));

                return;
            }

            $decisions[] = ['studentId' => $suggestion->studentId, 'status' => $status, 'note' => $suggestion->note];
        }

        if ($decisions === []) {
            $this->addError('decisions', __('Choose a status for at least one matched learner.'));

            return;
        }

        $result = app(ConfirmAttendanceReconciliationAction::class)->execute($preview['session']->id, $decisions, (int) auth()->id());

        $this->conflicts = $result->conflicts;
        $this->toast($result->conflicts === []
            ? __(':count learner(s) marked in the register.', ['count' => count($decisions)])
            : __('Marked, but :n learner(s) already had a different mark — those were left unchanged.', ['n' => count($result->conflicts)]), $result->conflicts === [] ? 'success' : 'warning');
    }

    public function render(): View
    {
        $studentIds = array_filter(array_column($this->suggestions, 'student_id'));

        return view('comms::meetings.attendance-review', [
            'meetings' => ScheduledMeeting::where('school_id', $this->school->id)
                ->where('meeting_type', 'online_lesson')->whereNotNull('timetable_slot_id')->where('starts_at', '<=', now())
                ->orderByDesc('starts_at')->limit(50)->get(['id', 'starts_at', 'duration_minutes', 'status']),
            'students' => Student::where('school_id', $this->school->id)->whereIn('id', $studentIds)->get()->keyBy('id'),
        ]);
    }
}
