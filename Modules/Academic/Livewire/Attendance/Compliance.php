<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Attendance;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\RecordMarkingComplianceAction;
use Modules\Academic\Domain\DataObjects\RecordMarkingComplianceData;
use Modules\Academic\Models\AttendanceMarkingCompliance;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Attendance\Compliance` (Book D ACA-04 §5/BR-ACA-04-014,
 * `academic.attendance.view_compliance`). "Unmarked registers appear
 * on the deputy head's dashboard the same day" — this screen computes
 * it on demand per teacher per date via `RecordMarkingComplianceAction`
 * rather than relying on a scheduled job this pass doesn't wire up.
 */
#[Title('Marking compliance')]
#[Layout('layouts.app')]
final class Compliance extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $date = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.attendance.view_compliance');

        $this->date = now()->toDateString();
    }

    public function recompute(): void
    {
        $termId = SessionContext::termId();

        if ($termId === null) {
            $this->toast(__('No active term is set for this school.'), 'danger');

            return;
        }

        $teachingStaff = Staff::where('school_id', $this->school->id)->where('is_teaching', true)->get();

        foreach ($teachingStaff as $staff) {
            app(RecordMarkingComplianceAction::class)->execute(new RecordMarkingComplianceData(
                staffId: $staff->id,
                termId: $termId,
                sessionDate: Carbon::parse($this->date),
            ));
        }

        $this->toast(__('Compliance recomputed for :count teacher(s).', ['count' => $teachingStaff->count()]));
    }

    public function render(): View
    {
        return view('academic::attendance.compliance', [
            'rows' => AttendanceMarkingCompliance::where('school_id', $this->school->id)
                ->whereDate('session_date', $this->date)
                ->with('staff')
                ->orderBy('compliance_percent')
                ->get(),
        ]);
    }
}
