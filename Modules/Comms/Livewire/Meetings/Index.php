<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Meetings;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Models\TimetableSlot;
use Modules\Comms\Domain\Actions\CancelScheduledMeetingAction;
use Modules\Comms\Domain\Actions\DisableWaitingRoomOverrideAction;
use Modules\Comms\Domain\Actions\ResolveMeetingHostCredentialsAction;
use Modules\Comms\Domain\Actions\ScheduleMeetingAction;
use Modules\Comms\Domain\DataObjects\ScheduleMeetingData;
use Modules\Comms\Domain\Exceptions\MeetingHostCredentialsNotAccessibleException;
use Modules\Comms\Domain\Exceptions\WaitingRoomOverrideRequiresApprovalException;
use Modules\Comms\Models\MeetingProvider;
use Modules\Comms\Models\ScheduledMeeting;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Comms\Meetings\Index` (Book I COM-07 §5, `meetings.view`). The
 * schedule, plus scheduling and cancelling (`meetings.manage`).
 *
 * `host_url` and `passcode` are never selected into the list
 * (BR-COM-07-001): they are fetched only through
 * `ResolveMeetingHostCredentialsAction`, which refuses anyone but the
 * meeting's own host, and are held in `$revealed` for that one
 * response only. A learner-facing (`online_lesson`) meeting always
 * starts with its waiting room on; turning it off is a separate,
 * logged override that needs `meetings.waiting_room.override` — a user
 * without it still triggers the Action, which logs the unauthorised
 * attempt (AC-COM-07-005) and refuses.
 */
#[Title('Meeting schedule')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $meetingType = 'staff_meeting';

    public ?int $providerId = null;

    public string $topic = '';

    public string $startsAt = '';

    public int $durationMinutes = 40;

    public ?int $hostStaffId = null;

    public ?int $timetableSlotId = null;

    public bool $waitingRoomEnabled = true;

    public bool $recordingEnabled = false;

    public string $statusFilter = 'scheduled';

    /**
     * @var array{meeting_id: int, host_url: ?string, passcode: ?string}|null
     */
    public ?array $revealed = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('meetings.view');
    }

    public function schedule(): void
    {
        $this->authorizePermission('meetings.manage');

        $this->validate([
            'meetingType' => ['required', 'in:online_lesson,consultation,staff_meeting,board_meeting,webinar'],
            'providerId' => ['required', 'integer'],
            'topic' => ['required', 'string', 'max:200'],
            'startsAt' => ['required', 'date', 'after:now'],
            'durationMinutes' => ['required', 'integer', 'min:5', 'max:480'],
            'hostStaffId' => ['nullable', 'integer'],
            'timetableSlotId' => [$this->meetingType === 'online_lesson' ? 'required' : 'nullable', 'integer'],
        ], ['timetableSlotId.required' => __('An online lesson must be linked to a timetable slot.')]);

        $provider = MeetingProvider::where('school_id', $this->school->id)->where('is_active', true)->findOrFail($this->providerId);
        $term = AcademicYear::where('school_id', $this->school->id)->where('is_current', true)->first()?->currentTerm();

        if ($term === null) {
            $this->addError('startsAt', __('There is no current term to schedule the meeting in.'));

            return;
        }

        if ($this->hostStaffId !== null) {
            Staff::where('school_id', $this->school->id)->findOrFail($this->hostStaffId);
        }

        if ($this->timetableSlotId !== null) {
            TimetableSlot::where('school_id', $this->school->id)->findOrFail($this->timetableSlotId);
        }

        try {
            app(ScheduleMeetingAction::class)->execute(new ScheduleMeetingData(
                schoolId: $this->school->id,
                termId: $term->id,
                meetingType: $this->meetingType,
                providerId: $provider->id,
                topic: $this->topic,
                startsAt: Carbon::parse($this->startsAt),
                durationMinutes: $this->durationMinutes,
                hostStaffId: $this->hostStaffId,
                timetableSlotId: $this->meetingType === 'online_lesson' ? $this->timetableSlotId : null,
                waitingRoomEnabled: $this->waitingRoomEnabled,
                recordingEnabled: $this->recordingEnabled,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('timetableSlotId', $exception->getMessage());

            return;
        }

        $this->reset(['topic', 'startsAt', 'hostStaffId', 'timetableSlotId', 'recordingEnabled']);
        $this->toast(__('Meeting scheduled.'));
    }

    public function cancel(int $meetingId): void
    {
        $this->authorizePermission('meetings.manage');

        $meeting = ScheduledMeeting::where('school_id', $this->school->id)->where('status', 'scheduled')->findOrFail($meetingId);
        app(CancelScheduledMeetingAction::class)->execute($meeting->id);

        $this->toast(__('Meeting cancelled. Booked participants are notified and consultation slots released.'));
    }

    public function disableWaitingRoom(int $meetingId): void
    {
        $this->authorizePermission('meetings.view');

        $meeting = ScheduledMeeting::where('school_id', $this->school->id)->where('meeting_type', 'online_lesson')->findOrFail($meetingId);
        $mayOverride = app(PermissionScopeResolver::class)->has(auth()->user(), 'meetings.waiting_room.override', PermissionScope::Own);

        try {
            app(DisableWaitingRoomOverrideAction::class)->execute($meeting->id, (int) auth()->id(), $mayOverride ? (int) auth()->id() : null);
        } catch (WaitingRoomOverrideRequiresApprovalException $exception) {
            $this->toast(__('You are not permitted to disable a learner meeting’s waiting room. The attempt has been logged.'), 'danger');

            return;
        }

        $this->toast(__('Waiting room disabled — the override has been logged.'), 'warning');
    }

    public function revealHostCredentials(int $meetingId): void
    {
        $this->authorizePermission('meetings.view');

        $meeting = ScheduledMeeting::where('school_id', $this->school->id)->findOrFail($meetingId);

        try {
            $credentials = app(ResolveMeetingHostCredentialsAction::class)->execute($meeting->id, (int) auth()->id());
        } catch (MeetingHostCredentialsNotAccessibleException) {
            $this->toast(__('Only this meeting’s host can see its host link and passcode.'), 'danger');

            return;
        }

        $this->revealed = ['meeting_id' => $meeting->id, 'host_url' => $credentials['host_url'], 'passcode' => $credentials['passcode']];
    }

    public function hideHostCredentials(): void
    {
        $this->revealed = null;
    }

    public function render(): View
    {
        $meetings = ScheduledMeeting::where('school_id', $this->school->id)
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('starts_at')
            ->limit(100)
            ->get(['id', 'meeting_type', 'provider_id', 'starts_at', 'duration_minutes', 'host_staff_id', 'waiting_room_enabled', 'recording_enabled', 'status', 'join_url']);

        $hosts = Staff::where('school_id', $this->school->id)->whereIn('id', $meetings->pluck('host_staff_id')->filter())->get()->keyBy('id');
        $term = AcademicYear::where('school_id', $this->school->id)->where('is_current', true)->first()?->currentTerm();

        return view('comms::meetings.index', [
            'meetings' => $meetings,
            'hosts' => $hosts,
            'providers' => MeetingProvider::where('school_id', $this->school->id)->where('is_active', true)->orderBy('provider')->get(['id', 'provider']),
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('last_name')->limit(300)->get(),
            'slots' => $term !== null
                ? TimetableSlot::with('subject:id,name')->where('school_id', $this->school->id)->where('term_id', $term->id)->orderBy('cycle_day')->orderBy('period_number')->limit(300)->get()
                : collect(),
            'canManage' => app(PermissionScopeResolver::class)->has(auth()->user(), 'meetings.manage', PermissionScope::Own),
        ]);
    }
}
