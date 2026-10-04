<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Projects;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Models\LegacyCalaRecord;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Projects\CalaArchive` (Book E ACA-06 §2/§6/BR-ACA-06-019,
 * `academic.projects.view`). Read-only, clearly labelled as archived —
 * `legacy_cala_records` is `SELECT`-only at the database grant level
 * (AC-ACA-06-009); this screen has no create/edit/delete method at all,
 * not merely a hidden one.
 */
#[Title('CALA archive')]
#[Layout('layouts.app')]
final class CalaArchive extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.projects.view');
    }

    public function render(): View
    {
        return view('academic::projects.cala-archive', [
            'records' => LegacyCalaRecord::where('school_id', $this->school->id)
                ->with('student', 'subject', 'academicYear')
                ->orderByDesc('academic_year_id')
                ->limit(200)
                ->get(),
        ]);
    }
}
