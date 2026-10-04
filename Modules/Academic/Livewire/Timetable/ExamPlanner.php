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
use Modules\Academic\Domain\Actions\CreateExamSlotPlanAction;
use Modules\Academic\Domain\DataObjects\CreateExamSlotPlanData;
use Modules\Academic\Models\ExamSlotPlan;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;

/**
 * `Timetable\ExamPlanner` (Book E ACA-03 §2/BR-ACA-03-020 🇿🇼,
 * `academic.timetable.manage`). Reserve a public-examination period and
 * view the disruption report `CreateExamSlotPlanAction` computes itself
 * by walking the published timetable (AC-ACA-03-009).
 */
#[Title('Exam slot planner')]
#[Layout('layouts.app')]
final class ExamPlanner extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $name = '';

    public string $examBody = 'zimsec';

    public string $startsOn = '';

    public string $endsOn = '';

    /** @var array<int, int> */
    public array $affectedLevels = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.timetable.manage');
    }

    public function create(): void
    {
        $this->authorizePermission('academic.timetable.manage');

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'examBody' => ['required', 'in:zimsec,cambridge,internal'],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['required', 'date', 'after_or_equal:startsOn'],
            'affectedLevels' => ['required', 'array', 'min:1'],
        ]);

        $termId = SessionContext::termId();

        if ($termId === null) {
            $this->toast(__('No active term is set for this school.'), 'danger');

            return;
        }

        app(CreateExamSlotPlanAction::class)->execute(new CreateExamSlotPlanData(
            schoolId: $this->school->id,
            termId: $termId,
            name: $this->name,
            examBody: $this->examBody,
            startsOn: Carbon::parse($this->startsOn),
            endsOn: Carbon::parse($this->endsOn),
            affectedLevels: $this->affectedLevels,
            createdByUserId: (int) Auth::id(),
        ));

        $this->reset(['name', 'startsOn', 'endsOn', 'affectedLevels']);
        $this->toast(__('Exam slot plan created.'));
    }

    public function render(): View
    {
        $subjectNames = Subject::where('school_id', $this->school->id)->pluck('name', 'id');
        $levelNames = GradeLevel::where('school_id', $this->school->id)->pluck('name', 'id');

        return view('academic::timetable.exam-planner', [
            'plans' => ExamSlotPlan::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'levels' => GradeLevel::where('school_id', $this->school->id)->orderBy('ordinal')->get(),
            'subjectNames' => $subjectNames,
            'levelNames' => $levelNames,
        ]);
    }
}
