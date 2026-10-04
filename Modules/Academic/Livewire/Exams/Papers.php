<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Exams;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateExaminationPaperAction;
use Modules\Academic\Domain\DataObjects\CreateExaminationPaperData;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\ExaminationSession;
use Modules\Academic\Models\Subject;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Exams\Papers` (Book E ACA-07 §2/§5/BR-ACA-07-004, `academic.exams.manage`).
 * List + create with a live weight-% total per subject/level, advisory
 * only — `CreateExaminationPaperAction`'s own docblock says the 100%
 * total is deliberately NOT checked at paper creation (a partial set is
 * normal mid-setup); `ProcessExaminationResultsAction` is where it
 * actually blocks (AC-ACA-07-011, surfaced on `Exams\Results`).
 */
#[Title('Examination papers')]
#[Layout('layouts.app')]
final class Papers extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $sessionId = null;

    public ?int $subjectId = null;

    public ?int $gradeLevelId = null;

    public string $paperNumber = '1';

    public string $paperName = '';

    public string $componentType = 'theory';

    public string $maxMark = '100';

    public string $weightPercent = '100';

    public string $durationMinutes = '120';

    public ?int $setterStaffId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.exams.manage');
    }

    public function create(): void
    {
        $this->validate([
            'sessionId' => ['required', 'integer'],
            'subjectId' => ['required', 'integer'],
            'gradeLevelId' => ['required', 'integer'],
            'paperNumber' => ['required', 'string'],
            'paperName' => ['required', 'string', 'max:120'],
            'maxMark' => ['required', 'numeric', 'min:1'],
            'weightPercent' => ['required', 'numeric', 'min:0', 'max:100'],
            'durationMinutes' => ['required', 'integer', 'min:1'],
        ]);

        app(CreateExaminationPaperAction::class)->execute(new CreateExaminationPaperData(
            schoolId: $this->school->id,
            sessionId: $this->sessionId,
            subjectId: $this->subjectId,
            gradeLevelId: $this->gradeLevelId,
            paperNumber: $this->paperNumber,
            paperName: $this->paperName,
            componentType: $this->componentType,
            maxMark: (float) $this->maxMark,
            weightPercent: (float) $this->weightPercent,
            durationMinutes: (int) $this->durationMinutes,
            setterStaffId: $this->setterStaffId,
        ));

        $this->reset(['paperName', 'setterStaffId']);
        $this->toast(__('Paper created.'));
    }

    public function render(): View
    {
        $papers = $this->sessionId !== null
            ? ExaminationPaper::where('session_id', $this->sessionId)->with('subject', 'gradeLevel')->get()
            : collect();

        $weightTotals = $papers->groupBy(fn (ExaminationPaper $p): string => "{$p->subject_id}:{$p->grade_level_id}")
            ->map(fn ($group) => (float) $group->sum('weight_percent'));

        return view('academic::exams.papers', [
            'sessions' => ExaminationSession::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'subjects' => Subject::where('school_id', $this->school->id)->orderBy('name')->get(),
            'gradeLevels' => GradeLevel::where('school_id', $this->school->id)->orderBy('ordinal')->get(),
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('first_name')->get(),
            'papers' => $papers,
            'weightTotals' => $weightTotals,
        ]);
    }
}
