<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Curriculum;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateLevelSubjectOfferingAction;
use Modules\Academic\Domain\DataObjects\CreateLevelSubjectOfferingData;
use Modules\Academic\Models\LevelSubjectOffering;
use Modules\Academic\Models\Pathway;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;

/**
 * `Curriculum\Offerings` (Book D ACA-01 §5/BR-ACA-01-006,
 * `academic.curriculum.manage` to create, `.view` to list). Per the
 * current academic year — "level offerings are per academic year;
 * subject availability changes year to year without disturbing
 * history" (BR-ACA-01-006). List + create, one row per level/subject
 * pair, rather than the spec's own full interactive grid — the same
 * "pick-and-submit over drag/grid" simplification this pass uses
 * throughout (see `.ai/rules/academic.md`).
 */
#[Title('Level subject offerings')]
#[Layout('layouts.app')]
final class Offerings extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $gradeLevelId = null;

    public ?int $subjectId = null;

    public ?int $pathwayId = null;

    public bool $isCompulsory = false;

    public bool $isAvailable = true;

    public string $periodsPerWeek = '';

    public string $maxLearners = '';

    public string $optionBlock = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.curriculum.view');
    }

    public function create(): void
    {
        $this->authorizePermission('academic.curriculum.manage');

        $this->validate([
            'gradeLevelId' => ['required', 'integer'],
            'subjectId' => ['required', 'integer'],
        ]);

        $yearId = SessionContext::yearId();

        if ($yearId === null) {
            $this->addError('gradeLevelId', __('No active academic year is set for this school.'));

            return;
        }

        app(CreateLevelSubjectOfferingAction::class)->execute(new CreateLevelSubjectOfferingData(
            schoolId: $this->school->id,
            academicYearId: $yearId,
            gradeLevelId: (int) $this->gradeLevelId,
            subjectId: (int) $this->subjectId,
            pathwayId: $this->pathwayId,
            isCompulsory: $this->isCompulsory,
            isAvailable: $this->isAvailable,
            periodsPerWeek: $this->periodsPerWeek !== '' ? (int) $this->periodsPerWeek : null,
            maxLearners: $this->maxLearners !== '' ? (int) $this->maxLearners : null,
            optionBlock: $this->optionBlock !== '' ? $this->optionBlock : null,
        ));

        $this->reset(['subjectId', 'pathwayId', 'isCompulsory', 'periodsPerWeek', 'maxLearners', 'optionBlock']);
        $this->toast(__('Offering created.'));
    }

    public function render(): View
    {
        $yearId = SessionContext::yearId();

        return view('academic::curriculum.offerings', [
            'offerings' => $yearId !== null
                ? LevelSubjectOffering::where('school_id', $this->school->id)->where('academic_year_id', $yearId)
                    ->with('gradeLevel', 'subject', 'pathway')->orderBy('grade_level_id')->get()
                : collect(),
            'gradeLevels' => GradeLevel::where('school_id', $this->school->id)->orderBy('ordinal')->get(),
            'subjects' => Subject::where('school_id', $this->school->id)->orderBy('name')->get(),
            'pathways' => Pathway::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
