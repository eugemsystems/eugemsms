<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Supervision;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\BuildTeacherDashboardAction;
use Modules\Academic\Domain\DataObjects\BuildTeacherDashboardData;
use Modules\Academic\Livewire\Concerns\ResolvesSupervisionReach;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * `Academic\Supervision\TeacherDashboard` (Book K ACA-11 §4/BR-ACA-11-009).
 * Coverage, lesson-plan submission and observation ratings per teacher, read
 * from the records that own them. A teacher sees their own; a head of
 * department sees their department; school-reach `supervision.view` sees all.
 * Marking-compliance and results outcomes are not wired in yet.
 */
#[Title('Teacher dashboard')]
#[Layout('layouts.app')]
final class TeacherDashboard extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesSupervisionReach;

    public ?int $termId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        abort_unless($this->holds('supervision.plan') || $this->holds('supervision.view'), 403);
        $this->termId = $this->currentTermId();
    }

    public function render(): View
    {
        $visible = $this->visibleStaffIds();
        $staff = Staff::query()->when($visible !== null, fn ($q) => $q->whereIn('id', $visible))->orderBy('last_name')->limit(200)->get();
        $term = $this->termId === null ? null : Term::query()->find($this->termId);

        $rows = $term === null ? [] : $staff->map(fn (Staff $member): array => [
            'staff' => $member,
            'summary' => app(BuildTeacherDashboardAction::class)->execute(new BuildTeacherDashboardData($member->id, $term->id)),
        ])->all();

        return view('academic::supervision.dashboard', [
            'rows' => $rows,
            'terms' => Term::query()->orderByDesc('starts_on')->limit(8)->get(['id', 'name']),
        ]);
    }
}
