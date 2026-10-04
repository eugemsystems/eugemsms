<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Timetable;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\BuildTimetableRequirementsAction;
use Modules\Academic\Domain\DataObjects\BuildTimetableRequirementsData;
use Modules\Academic\Models\PeriodSlot;
use Modules\Academic\Models\PeriodStructure;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Timetable\Requirements` (Book E ACA-03 §7, `academic.timetable.manage`
 * per the spec's own screen table). Read-only — `BuildTimetableRequirementsAction`
 * only builds the requirement list from `PPL-04`'s `TeacherAllocation`
 * rows (whole-class requirements; teaching-group/set requirements are
 * NOT derived — see that action's own docblock), it has no feasibility
 * -scoring logic of its own. This screen adds one simple, honestly-labelled
 * heuristic on top — periods requested vs. the number of teachable slots
 * that exist in the school's default structure — rather than fabricating
 * the spec's own "feasibility warnings" as if a real constraint solver
 * produced them.
 */
#[Title('Timetable requirements')]
#[Layout('layouts.app')]
final class Requirements extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.timetable.manage');
    }

    public function render(): View
    {
        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        $requirements = collect();
        $teachableSlotCount = 0;

        if ($yearId !== null && $termId !== null) {
            $requirements = app(BuildTimetableRequirementsAction::class)->execute(new BuildTimetableRequirementsData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
            ));

            $structure = PeriodStructure::where('school_id', $this->school->id)->where('is_default', true)->first();
            $teachableSlotCount = $structure !== null
                ? PeriodSlot::where('structure_id', $structure->id)->where('is_teachable', true)->count()
                : 0;
        }

        $staffNames = Staff::whereIn('id', $requirements->pluck('staffId')->unique())->pluck('first_name', 'id');
        $subjectNames = Subject::whereIn('id', $requirements->pluck('subjectId')->unique())->pluck('name', 'id');

        return view('academic::timetable.requirements', [
            'requirements' => $requirements,
            'teachableSlotCount' => $teachableSlotCount,
            'staffNames' => $staffNames,
            'subjectNames' => $subjectNames,
        ]);
    }
}
