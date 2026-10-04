<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Curriculum;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateSubjectPrerequisiteAction;
use Modules\Academic\Domain\DataObjects\CreateSubjectPrerequisiteData;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectPrerequisite;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Curriculum\Prerequisites` (Book D ACA-01 §5/BR-ACA-01-012,
 * `academic.curriculum.manage` to create, `.view` to list).
 */
#[Title('Subject prerequisites')]
#[Layout('layouts.app')]
final class Prerequisites extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $subjectId = null;

    public ?int $prerequisiteSubjectId = null;

    public string $severity = 'warn';

    public string $minimumGrade = '';

    public string $examination = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.curriculum.view');
    }

    public function create(): void
    {
        $this->authorizePermission('academic.curriculum.manage');

        $this->validate([
            'subjectId' => ['required', 'integer'],
            'prerequisiteSubjectId' => ['required', 'integer', 'different:subjectId'],
            'severity' => ['required', 'in:block,warn'],
        ]);

        app(CreateSubjectPrerequisiteAction::class)->execute(new CreateSubjectPrerequisiteData(
            schoolId: $this->school->id,
            subjectId: (int) $this->subjectId,
            prerequisiteSubjectId: (int) $this->prerequisiteSubjectId,
            severity: $this->severity,
            minimumGrade: $this->minimumGrade !== '' ? $this->minimumGrade : null,
            examination: $this->examination !== '' ? $this->examination : null,
        ));

        $this->reset(['subjectId', 'prerequisiteSubjectId', 'minimumGrade', 'examination']);
        $this->toast(__('Prerequisite created.'));
    }

    public function render(): View
    {
        return view('academic::curriculum.prerequisites', [
            'prerequisites' => SubjectPrerequisite::where('school_id', $this->school->id)->with('subject', 'prerequisiteSubject')->get(),
            'subjects' => Subject::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
