<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Supervision;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\RecordDepartmentMeetingAction;
use Modules\Academic\Domain\Actions\UpdateActionItemStatusAction;
use Modules\Academic\Domain\DataObjects\RecordDepartmentMeetingData;
use Modules\Academic\Domain\DataObjects\UpdateActionItemStatusData;
use Modules\Academic\Livewire\Concerns\ResolvesSupervisionReach;
use Modules\Academic\Models\DepartmentMeeting;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Department;
use Modules\People\Models\Staff;

/**
 * `Academic\Supervision\Meetings` (Book K ACA-11 §4, `supervision.meeting.manage`).
 * Minutes with owner-and-due-date action items. The action-item board lists
 * every item across meetings by status so accountability can be checked
 * without reopening the minutes (BR-ACA-11-008, AC-ACA-11-005); an item's
 * owner, or anyone who manages meetings, can move it along.
 */
#[Title('Department meetings')]
#[Layout('layouts.app')]
final class Meetings extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesSupervisionReach;
    use Toasts;

    public ?int $departmentId = null;

    public string $meetingDate = '';

    /** @var array<int, int|string> */
    public array $attendees = [];

    public ?int $chairId = null;

    public string $agenda = '';

    public string $minutes = '';

    /** @var array<int, array{action: string, owner: string, due: string}> */
    public array $actionRows = [];

    public string $statusFilter = 'open';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        abort_unless($this->holds('supervision.meeting.manage') || $this->holds('supervision.view') || $this->holds('supervision.plan'), 403);
        $this->meetingDate = now()->toDateString();
    }

    public function addActionRow(): void
    {
        $this->actionRows[] = ['action' => '', 'owner' => '', 'due' => ''];
    }

    public function removeActionRow(int $index): void
    {
        unset($this->actionRows[$index]);
        $this->actionRows = array_values($this->actionRows);
    }

    public function record(): void
    {
        $this->authorizePermission('supervision.meeting.manage');
        $this->resetErrorBag();

        if ($this->departmentId === null || $this->chairId === null) {
            $this->addError('minutes', __('Choose the department and who chaired.'));

            return;
        }

        $items = array_map(fn (array $row): array => ['action' => trim($row['action']), 'owner' => (int) $row['owner'], 'due_date' => $row['due']], array_filter($this->actionRows, fn (array $row): bool => trim($row['action']) !== ''));

        try {
            app(RecordDepartmentMeetingAction::class)->execute(new RecordDepartmentMeetingData(
                schoolId: $this->school->id, departmentId: $this->departmentId, meetingDate: Carbon::parse($this->meetingDate),
                attendeeStaffIds: array_map('intval', $this->attendees), minutes: $this->minutes, chairedByStaffId: $this->chairId,
                agenda: trim($this->agenda) === '' ? null : trim($this->agenda), actionItems: $items === [] ? null : array_values($items),
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('minutes', $exception->getMessage());

            return;
        }

        $this->reset('attendees', 'agenda', 'minutes', 'actionRows');
        $this->toast(__('Minutes recorded.'));
    }

    public function setStatus(int $meetingId, int $itemIndex, string $status): void
    {
        $meeting = DepartmentMeeting::query()->find($meetingId);
        $owner = (int) ($meeting?->action_items[$itemIndex]['owner'] ?? 0);
        $own = $this->ownStaff();

        if ($meeting === null || ! ($this->holds('supervision.meeting.manage') || ($own !== null && $own->id === $owner))) {
            abort(403);
        }

        try {
            app(UpdateActionItemStatusAction::class)->execute(new UpdateActionItemStatusData($meeting->id, $itemIndex, $status));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');
        }
    }

    public function render(): View
    {
        $own = $this->ownStaff();
        $seesAll = $this->seesEveryone() || $this->holds('supervision.meeting.manage');
        $headed = $own === null ? collect() : Department::query()->where('head_staff_id', $own->id)->pluck('id');

        $meetings = DepartmentMeeting::query()
            ->when(! $seesAll, fn ($q) => $q->where(fn ($w) => $w->whereIn('department_id', $headed)->when($own !== null, fn ($o) => $o->orWhere('chaired_by', $own?->id)->orWhereJsonContains('attendee_staff_ids', $own?->id))))
            ->orderByDesc('meeting_date')->limit(40)->get();

        $board = [];

        foreach ($meetings as $meeting) {
            foreach ($meeting->action_items ?? [] as $index => $item) {
                if ($this->statusFilter === '' || ($item['status'] ?? 'open') === $this->statusFilter) {
                    $board[] = ['meeting' => $meeting, 'index' => $index, 'item' => $item];
                }
            }
        }

        usort($board, fn (array $a, array $b): int => strcmp((string) ($a['item']['due_date'] ?? ''), (string) ($b['item']['due_date'] ?? '')));

        return view('academic::supervision.meetings', [
            'meetings' => $meetings,
            'board' => $board,
            'canManage' => $this->holds('supervision.meeting.manage'),
            'ownStaffId' => $own?->id,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'departmentNames' => Department::query()->pluck('name', 'id'),
            'staff' => Staff::query()->orderBy('last_name')->limit(400)->get(),
            'staffNames' => Staff::query()->get()->mapWithKeys(fn (Staff $s): array => [$s->id => $s->fullName()]),
        ]);
    }
}
