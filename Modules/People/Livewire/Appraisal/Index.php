<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Appraisal;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\CreateStaffAppraisalAction;
use Modules\People\Domain\DataObjects\CreateStaffAppraisalData;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffAppraisal;

/**
 * `People\Appraisal\Index` (Book C PPL-04 §5, `people.staff.appraisal_manage`).
 * List + create — the rest of the cycle (self-assessment, appraiser
 * assessment, meeting, sign-off) lives on `People\Appraisal\Show`, one
 * lifecycle screen per appraisal, same shape as `Admissions\Applications\Show`.
 */
#[Title('Appraisals')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $staffId = null;

    public string $cycle = 'annual';

    public ?int $appraiserStaffId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('people.staff.appraisal_manage');
    }

    public function create(): void
    {
        $this->validate([
            'staffId' => ['required', 'integer'],
            'cycle' => ['required', 'in:probation,mid_year,annual'],
            'appraiserStaffId' => ['required', 'integer'],
        ]);

        $yearId = SessionContext::yearId();

        if ($yearId === null) {
            $this->addError('staffId', __('No active academic year is set for this school.'));

            return;
        }

        $appraisal = app(CreateStaffAppraisalAction::class)->execute(new CreateStaffAppraisalData(
            schoolId: $this->school->id,
            staffId: (int) $this->staffId,
            academicYearId: $yearId,
            cycle: $this->cycle,
            appraiserStaffId: (int) $this->appraiserStaffId,
        ));

        $this->redirectRoute('people.appraisal.show', ['school' => $this->school, 'appraisal' => $appraisal], navigate: true);
    }

    public function render(): View
    {
        return view('people::appraisal.index', [
            'staffList' => Staff::where('school_id', $this->school->id)->orderBy('first_name')->get(),
            'appraisals' => StaffAppraisal::where('school_id', $this->school->id)->with('staff', 'appraiser')->orderByDesc('id')->get(),
        ]);
    }
}
