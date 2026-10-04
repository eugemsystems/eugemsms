<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Attendance;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Models\AttendanceSession;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Attendance\Daily` (Book D ACA-04 §5, `academic.attendance.view`).
 * Every register for a chosen date: marked, partial, or missing.
 */
#[Title('Daily attendance overview')]
#[Layout('layouts.app')]
final class Daily extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $date = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.attendance.view');

        $this->date = now()->toDateString();
    }

    public function render(): View
    {
        return view('academic::attendance.daily', [
            'sessions' => AttendanceSession::where('school_id', $this->school->id)
                ->whereDate('session_date', $this->date)
                ->with('schoolClass')
                ->orderBy('class_id')
                ->get(),
        ]);
    }
}
