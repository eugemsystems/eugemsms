<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Timetable;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateTimetableExceptionAction;
use Modules\Academic\Domain\DataObjects\CreateTimetableExceptionData;
use Modules\Academic\Models\TimetableException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;

/**
 * `Timetable\Exceptions` (Book E ACA-03 §2/§6/BR-ACA-03-021,
 * `academic.timetable.manage`). List + create. `suppresses_attendance`
 * is the lever `GenerateAttendanceSessionsFromTimetableAction` reads
 * directly (AC-ACA-03-010) — surfaced here as a plain checkbox.
 */
#[Title('Timetable exceptions')]
#[Layout('layouts.app')]
final class Exceptions extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $exceptionDate = '';

    public string $exceptionType = 'no_lessons';

    public string $affectedScope = 'whole_school';

    public string $scopeId = '';

    public string $reason = '';

    public bool $suppressesAttendance = true;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.timetable.manage');
    }

    public function create(): void
    {
        $this->authorizePermission('academic.timetable.manage');

        $this->validate([
            'exceptionDate' => ['required', 'date'],
            'exceptionType' => ['required', 'string'],
            'affectedScope' => ['required', 'in:whole_school,section,level,class'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $termId = SessionContext::termId();

        if ($termId === null) {
            $this->toast(__('No active term is set for this school.'), 'danger');

            return;
        }

        app(CreateTimetableExceptionAction::class)->execute(new CreateTimetableExceptionData(
            schoolId: $this->school->id,
            termId: $termId,
            exceptionDate: Carbon::parse($this->exceptionDate),
            exceptionType: $this->exceptionType,
            affectedScope: $this->affectedScope,
            reason: $this->reason,
            createdByUserId: (int) Auth::id(),
            scopeId: $this->scopeId !== '' ? (int) $this->scopeId : null,
            suppressesAttendance: $this->suppressesAttendance,
        ));

        $this->reset(['exceptionDate', 'scopeId', 'reason']);
        $this->toast(__('Exception recorded.'));
    }

    public function render(): View
    {
        return view('academic::timetable.exceptions', [
            'exceptions' => TimetableException::where('school_id', $this->school->id)
                ->orderByDesc('exception_date')
                ->limit(100)
                ->get(),
        ]);
    }
}
