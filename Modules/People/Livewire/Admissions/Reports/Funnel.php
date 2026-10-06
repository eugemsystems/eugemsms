<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Admissions\Reports;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\BuildAdmissionsFunnelAction;
use Modules\People\Models\Intake;

/**
 * `Admissions\Reports\Funnel` (Book C PPL-02 §5, `people.admissions.report_view`).
 * Enquiry to application to offer to acceptance to enrolment, with the reasons
 * enquiries were lost.
 */
#[Title('Admissions funnel')]
#[Layout('layouts.app')]
final class Funnel extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public ?int $intakeId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.admissions.report_view');
    }

    public function render(): View
    {
        return view('people::admissions.funnel', [
            'funnel' => app(BuildAdmissionsFunnelAction::class)->execute($this->intakeId),
            'intakes' => Intake::query()->orderByDesc('id')->get(['id', 'name']),
        ]);
    }
}
