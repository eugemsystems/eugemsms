<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Detentions;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\RecordDetentionAttendanceAction;
use Modules\Welfare\Domain\Actions\ScheduleDetentionAction;
use Modules\Welfare\Domain\DataObjects\ScheduleDetentionData;
use Modules\Welfare\Models\Detention;

/**
 * `Detentions\Register` (Book G BRD-07 §5 ⭐, `behaviour.detention.manage`).
 * Schedule, supervise, mark attendance. Closes the `detention`
 * roll-status stub (BR-BRD-07-011). A sports-fixture clash check is
 * deliberately not implemented — no fixture/timetable table exists yet
 * (`OPS-07`, not built), matching `ScheduleDetentionAction`'s own
 * docblock.
 */
#[Title('Detention register')]
#[Layout('layouts.app')]
final class Register extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public string $scheduledDate = '';

    public string $startsAt = '15:30';

    public string $endsAt = '16:30';

    public ?string $venue = null;

    public ?string $taskSet = null;

    public ?string $attendanceNote = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('behaviour.detention.manage');

        $this->scheduledDate = now()->toDateString();
    }

    public function schedule(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'scheduledDate' => ['required', 'date'],
            'startsAt' => ['required'],
            'endsAt' => ['required'],
        ]);

        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        app(ScheduleDetentionAction::class)->execute(new ScheduleDetentionData(
            schoolId: $this->school->id,
            termId: $term->id,
            studentId: (int) $this->studentId,
            scheduledDate: Carbon::parse($this->scheduledDate),
            startsAt: $this->startsAt,
            endsAt: $this->endsAt,
            venue: $this->venue,
            taskSet: $this->taskSet,
        ));

        $this->reset(['venue', 'taskSet']);
        $this->toast(__('Detention scheduled.'));
    }

    public function markAttendance(int $detentionId, bool $attended): void
    {
        app(RecordDetentionAttendanceAction::class)->execute($detentionId, $attended, $this->attendanceNote);

        $this->reset(['attendanceNote']);
        $this->toast($attended ? __('Attendance recorded.') : __('Marked missed.'));
    }

    public function render(): View
    {
        return view('welfare::detentions.register', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
            'detentions' => Detention::where('school_id', $this->school->id)
                ->with('student:id,first_name,last_name')
                ->orderByDesc('scheduled_date')
                ->limit(100)
                ->get(),
        ]);
    }
}
