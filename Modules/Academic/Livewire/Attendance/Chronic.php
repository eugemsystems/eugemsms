<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Attendance;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Models\AttendanceSummary;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;

/**
 * `Attendance\Chronic` (Book D ACA-04 §5/BR-ACA-04-013,
 * `academic.attendance.view`). Reads `attendance_summaries` — a
 * learner only appears here once `RebuildAttendanceSummaryAction` has
 * run for them this term (nightly in production; this pass has no
 * scheduled cron wiring it up, so the figures reflect whenever it was
 * last run).
 */
#[Title('Chronic absentees')]
#[Layout('layouts.app')]
final class Chronic extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.attendance.view');
    }

    public function render(): View
    {
        $termId = SessionContext::termId();

        return view('academic::attendance.chronic', [
            'summaries' => $termId !== null
                ? AttendanceSummary::where('term_id', $termId)->where('scope', 'term')->where('is_chronic_absentee', true)->with('student')->orderBy('attendance_percent')->get()
                : collect(),
        ]);
    }
}
